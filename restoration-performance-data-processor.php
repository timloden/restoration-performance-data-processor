<?php
/**
 * Plugin Name:     Restoration Performance Data Processor
 * Plugin URI:      https://restorationperformance.com
 * Description:     Plugin for processing vendor data to update stock and pricing
 * Author:          Tim Loden
 * Author URI:      https://timloden.com
 * Text Domain:     restoration-performance-data-processor
 * Domain Path:     /languages
 * Version:         1.11.1
 *
 * @package         Restoration_Performance_Data_Processor
 */

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/timloden/restoration-performance-data-processor',
	__FILE__,
	'restoration-performance-data-processor'
);

$myUpdateChecker->getVcsApi()->enableReleaseAssets();

require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\CannotInsertRecord;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

// Admin Page

function dbi_load_carbon_fields() {
    \Carbon_Fields\Carbon_Fields::boot();
}
add_action( 'after_setup_theme', 'dbi_load_carbon_fields' );

function dbi_add_plugin_settings_page() {
    Container::make( 'theme_options', __( 'RP Data Sources' ) )
        ->set_page_parent( 'options-general.php' )
        ->add_fields( array(
            Field::make( 'separator', 'crb_general_separator', __( 'General FTP' ) ),
            Field::make( 'text', 'general_host', 'General Hostname' )->set_width( 33 ),
            Field::make( 'text', 'general_user', 'General Username' )->set_width( 33 ),
            Field::make( 'text', 'general_pass', 'General Password' )->set_width( 33 ),
            Field::make( 'separator', 'crb_oer_separator', __( 'OER' ) ),
            Field::make( 'text', 'oer_export', 'OER Export URL' ),
            Field::make( 'text', 'oer_file_name', 'OER File Name' ),
            Field::make( 'text', 'oer_0_to_20', __( '$0 - $20' ) )->set_width( 25 ),
            Field::make( 'text', 'oer_20_to_50', __( '$20 - $50' ) )->set_width( 25 ),
            Field::make( 'text', 'oer_50_to_150', __( '$50 - $150' ) )->set_width( 25 ),
            Field::make( 'text', 'oer_150_plus', __( '$150+' ) )->set_width( 25 ),
            Field::make( 'separator', 'crb_dynacorn_separator', __( 'Dynacorn' ) ),
            Field::make( 'text', 'dii_export', 'DII Export URL' ),
            Field::make( 'text', 'dii_0_to_15', __( '$0 - $15' ) )->set_width( 20 ),
            Field::make( 'text', 'dii_15_to_70', __( '$15 - $70' ) )->set_width( 20 ),
            Field::make( 'text', 'dii_70_to_175', __( '$70 - $175' ) )->set_width( 20 ),
            Field::make( 'text', 'dii_175_to_800', __( '$175 - $800' ) )->set_width( 20 ),
            Field::make( 'text', 'dii_800_plus', __( '$800+' ) )->set_width( 20 ),
            Field::make( 'separator', 'crb_goodmark_separator', __( 'Goodmark FTP' ) ),
            Field::make( 'text', 'goodmark_host', 'Goodmark Hostname' )->set_width( 33 ),
            Field::make( 'text', 'goodmark_user', 'Goodmark Username' )->set_width( 33 ),
            Field::make( 'text', 'goodmark_pass', 'Goodmark Password' )->set_width( 33 ),
            Field::make( 'text', 'goodmark_export', 'Goodmark Export URL' ),
        ) );
}
add_action( 'carbon_fields_register_fields', 'dbi_add_plugin_settings_page' );

// WP CLI Commands

class RP_CLI {

    public function get_backorders() {
        //WP_CLI::line( 'give us backorders' );
        
        $orders = wc_get_orders(array(
            'limit'=>-1,
            'type'=> 'shop_order',
            'status'=> array( 'wc-on-backorder'),
            )
        );

        $body = get_bloginfo( 'name' ) . ' - ' . date("m/d/Y") . '<br><br>';

        foreach ( $orders as $order ) {

            $body .= 'PO #' . $order->get_id() . '<br>';
            foreach ( $order->get_items() as $item_id => $item ) {
                
                $product = $item->get_product();
                if ($product) {
                    $sku = $product->get_sku();
                    //$name = $product->get_name();
                    $stock_status = $product->get_stock_status();
    
                    $body .= $sku . ' | ' . $stock_status  . '<br>';
                }
            }
            $body .= '-----------------------------------------<br><br>';
        }

        //WP_CLI::line( $body );

        $to = 'tloden@restorationperformance.com,jlawson@restorationperformance.com,mlawson@restorationperformance.com';
        $subject = get_bloginfo( 'name' ) . ' Backorders - ' . date("m/d/Y");
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $body, $headers );
    }

    // Dynacorn

    public function download_existing_dynacorn() {
        $export_url = get_option( '_dii_export' );
        
        if ($export_url) {
            $uploads = wp_upload_dir();
            $dir = $uploads['basedir'] . '/vendors/dynacorn/';

            // Initialize a file URL to the variable 
            $url = $export_url; 
            
            $fremote = fopen($url, 'rb');
            
            if (!$fremote) {
                WP_CLI::error( 'There was a problem opening the export url' );
                return false;
            }

            $flocal = fopen($dir . 'dii-existing.csv', 'wb');
            if (!$flocal) {
                fclose($fremote);
                WP_CLI::error( 'There was a problem opening local' );
                return false;
            }

            while ($buffer = fread($fremote, 1024)) {
                fwrite($flocal, $buffer);
            }

            WP_CLI::success( 'Successfully written to ' . $dir );
        
            fclose($flocal);
            fclose($fremote);
        } else {
            WP_CLI::error( 'No export URL provided' );
        }
        
    }

    public function download_dynacorn() {
        
        // define our files
        $local_file = 'dynacorn-temp.xls';
        $server_file = 'DynacornInventory.xls';
        $finished_file = 'dynacorn-temp.csv';   

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/dynacorn/';

        $ftp_server = get_option( '_general_host' );
        $ftp_user_name = get_option( '_general_user' );
        $ftp_user_pass = get_option( '_general_pass' );

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        // try to download $server_file and save to $local_file
        if (ftp_get($conn_id, $dir.$local_file, $server_file, FTP_BINARY)) {
            // echo "Successfully written to $local_file\n";
            WP_CLI::line( 'Downloading...' );
            WP_CLI::success( 'Successfully written to ' . $dir . $local_file );

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($dir . $local_file );
            $spreadsheet = $reader->load($dir . $local_file);

            //print_r($spreadsheet);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->save($dir . $finished_file);
            
        } else {
            WP_CLI::error( 'There was a problem' );
        }

        // close the connection
        ftp_close($conn_id);
    }

    public function process_dynacorn() {

        $export_url = get_option( '_dii_export' );
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/dynacorn/';
        $pre_processed_file = $dir . 'dynacorn-temp.csv';    
        $processed_file = $dir . 'dynacorn-processed.csv';

        // get file
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // ignore header rows
        $reader->setHeaderOffset(0);
       
        // get all the existing records
        $records = $reader->getRecords();

        // array used to compare feed sku vs on site sku
        $current_products = [];

        if ($export_url) { 
            $existing_file = $dir . 'dii-existing.csv'; 
            $existing_reader = Reader::createFromPath($existing_file, 'r');
            $existing_reader->setHeaderOffset(0);
            $existing_records = $existing_reader->getRecords();

            foreach ($existing_records as $offset_record => $existing_record) {
                $sku_temp  = trim($existing_record['Sku']);
                array_push( $current_products, ['sku' => $sku_temp, 'shipping_class' => $existing_record['Shipping Class'], 'product_id' => $existing_record['ID']] );
            }
        }
        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['ItemNumber', 'Price', 'CAQuantity', 'PAQuantity', 'Weight', 'StockStatus', 'SalePrice', 'product_id']);
        
        // loop through the DII feed
        foreach ($records as $offset => $record) {
       
            $sku = trim($record['ItemNumber']);
            $key = $this->array_search_multidim($current_products, 'sku', $sku);

            if ($key) {
                
                $current_shipping_class = $current_products[$key]['shipping_class'];
                $product_id = $current_products[$key]['product_id'];
                $other_sku = $current_products[$key]['sku'];
                
                $weight = $record['Weight'];

                if ($current_shipping_class == 'Ground - Dyancorn - Oversized' && $weight < 30) {
                    $weight = 30;
                }


                $shipping_class_output = 'ground';
                 
                $cost = $record['Price'];
 
                $price = 0;
 
                $margin_dii_0_to_15 = get_option( '_dii_0_to_15' );
                $margin_dii_15_to_70 = get_option( '_dii_15_to_70' );
                $margin_dii_70_to_175 = get_option( '_dii_70_to_175' );
                $margin_dii_175_to_800 = get_option( '_dii_175_to_800' );
                $margin_dii_800_plus = get_option( '_dii_800_plus' );

                if ($cost <= 15) {
                    $price = (round($cost * $margin_dii_0_to_15)) - 0.05;
                } elseif ($cost > 15 && $cost <= 70) {
                    $price = (round($cost * $margin_dii_15_to_70)) - 0.05;
                } elseif ($cost > 70 && $cost <= 175) {
                    $price = (round($cost * $margin_dii_70_to_175)) - 0.05;
                } elseif ($cost > 175 && $cost <= 800) {
                    $price = (round($cost * $margin_dii_175_to_800)) - 0.05;
                } elseif ($cost > 800) {
                    $price = (round($cost * $margin_dii_800_plus)) - 0.05;
                }

                $ca_quantity = $record['CAQuantity'];
                $pa_quantity = $record['PAQuantity'];

                $total_quantity = $ca_quantity + $pa_quantity;

                $stock = 'onbackorder';
                
                if ($current_shipping_class == 'Dynacorn Freight' || $current_shipping_class == 'Ground - Dyancorn - Oversized' && $ca_quantity >= 1 && $pa_quantity >= 1) {
                    $stock = 'instock';
                } else if ($current_shipping_class != 'Dynacorn Freight' && $current_shipping_class != 'Ground - Dyancorn - Oversized') {
                    $stock = 'instock';
                }

                // add part to new csv
                $writer->insertOne([$sku, $cost, $ca_quantity, $pa_quantity, $weight, $stock, $price, $product_id]);

            }
               
        }

        $lines = array();

        // open the processed csv file
        if (($handle = fopen($processed_file, "r")) !== false) {
            // read each line into an array
            while (($data = fgetcsv($handle, 8192, ",")) !== false) {
                // build a "line" from the parsed data
                $line = join(",", $data);

                // if the line has been seen, skip it
                if (isset($lines[$line])) continue;

                // save the line
                $lines[$line] = true;
            }
            fclose($handle);
        }

        // build the new content-data
        $contents = '';
        foreach ($lines as $line => $bool) $contents .= $line . "\r\n";

        $finished_file = 'dynacorn-inventory-update.csv';

        // save it to a new file
        file_put_contents("$dir/$finished_file", $contents);
        
        WP_CLI::success( 'Successfully created ' . $finished_file );
    }

    private function array_search_multidim($array, $column, $key){
        return (array_search($key, array_column($array, $column)));
    }
    

    // OER

    public function download_existing_oer() {
        $export_url = get_option( '_oer_export' );
        
        if ($export_url) {
            $uploads = wp_upload_dir();
            $dir = $uploads['basedir'] . '/vendors/oer/';

            // Initialize a file URL to the variable 
            $url = $export_url; 
            
            $fremote = fopen($url, 'rb');
            if (!$fremote) {
                WP_CLI::error( 'There was a problem opening the export url' );
                return false;
            }

            $flocal = fopen($dir . 'oer-existing.csv', 'wb');
            if (!$flocal) {
                fclose($fremote);
                WP_CLI::error( 'There was a problem opening local' );
                return false;
            }

            while ($buffer = fread($fremote, 1024)) {
                fwrite($flocal, $buffer);
            }

            WP_CLI::success( 'Successfully written to ' . $dir );
        
            fclose($flocal);
            fclose($fremote);
        } else {
            WP_CLI::error( 'No export URL provided' );
        }
        
    }

	public function download_oer() {
        
        // define our files
        $local_file = 'oer-temp.csv';
        //$server_file = 'RPC.csv';
        $server_file = get_option( '_oer_file_name' );

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/oer/';

        $ftp_server = get_option( '_general_host' );
        $ftp_user_name = get_option( '_general_user' );
        $ftp_user_pass = get_option( '_general_pass' );

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        // try to download $server_file and save to $local_file
        if (ftp_get($conn_id, $dir.$local_file, $server_file, FTP_BINARY)) {
            // echo "Successfully written to $local_file\n";
            WP_CLI::line( 'Downloading...' );
            WP_CLI::success( 'Successfully written to ' . $dir . $local_file );
        } else {
            WP_CLI::error( 'There was a problem' );
        }

        // close the connection
        ftp_close($conn_id);
    }
    
    public function process_oer() {

        $export_url = get_option( '_oer_export' );
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/oer/';
        $pre_processed_file = $dir . 'oer-temp.csv';    
        
        // get files
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // ignore header rows
        $reader->setHeaderOffset(0);
       
        // get all the existing records
        $records = $reader->getRecords();

        $processed_file = $dir . 'oer-processed.csv';


         // file downloaded from download_existing_oer()

        if ($export_url) { 
         
            $existing_file = $dir . 'oer-existing.csv'; 
            $existing_reader = Reader::createFromPath($existing_file, 'r');
            $existing_reader->setHeaderOffset(0);
            $existing_records = $existing_reader->getRecords();

        }

        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['PartNumber', 'Cost', 'AvailableQty', 'Weight', 'Shipping Class', 'Brand', 'Quantity', 'Retail', 'Price']);

        // array used to compare feed sku vs on site sku
        $current_products = [];
        
        // loop through the OER feed
        foreach ($records as $offset => $record) {
       
            $sku = $record['PartNumber'];
            $sub_sub_category_name = trim($record['subsubcategoryname']);

            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);

            array_push( $current_products, $sku );

            $weight = $record['WeightLbs'];
            $shipping_class = $record['ShipType'];
            $shipping_class_output = 'ground';
            
            if ($shipping_class == 'Truck' && $record['ProductHeight'] > 70 && $sub_sub_category_name != 'Windshields') {
                $shipping_class_output = 'heavy-freight';

            } elseif ($shipping_class == 'Truck' && $sub_sub_category_name == 'Windshields') {
                $weight = $weight;
                $shipping_class_output = 'windshield';

            } elseif ($shipping_class == 'Truck') {
                $weight = $weight;
                $shipping_class_output = 'oer-freight';

            } elseif ($shipping_class == 'Oversize') {
                $weight = 30;
                $shipping_class_output = 'ground-oversized';

            } elseif ($shipping_class == 'Oversize-2') {
                $weight = 70;
                $shipping_class_output = 'ground-oversized';

            } elseif ($shipping_class == 'Oversize-3') {
                $weight = 50;
                $shipping_class_output = 'ground-oversized';

            } elseif ($shipping_class == 'Overweight' && $weight < 90) {
                $weight = 90;
                $shipping_class_output = 'ground-oversized';
                
            }

            if ($weight == 0) {
                $weight = 1;
            }

            // oer penny shipping for wheels
            if ($weight >= 100 && $shipping_class == 'Standard') {
                $weight = 50;
            } 
            
            $cost = $record['Cost'];

            $retail = $record['MSRP'];

            $price = $retail;

            $margin_oer_0_to_120 = get_option( '_oer_0_to_20' );
            $margin_oer_20_to_50 = get_option( '_oer_20_to_50' );
            $margin_oer_50_to_150 = get_option( '_oer_50_to_150' );
            $margin_oer_150_plus = get_option( '_oer_150_plus' );

            if ($cost <= 20) {
                $price = (round($cost * $margin_oer_0_to_120)) - 0.05;
            } elseif ($cost > 20 && $cost <= 50) {
                $price = (round($cost * $margin_oer_20_to_50)) - 0.05;
            } elseif ($cost > 50 && $cost <= 150) {
                $price = (round($cost * $margin_oer_50_to_150)) - 0.05;
            } elseif ($cost > 150) {
                $price = (round($cost * $margin_oer_150_plus)) - 0.05;
            }

            $brand = trim($record['Brand']);

            if ($brand == 'OER Authorized' && $cost > 250) {
                $price = $retail - 0.04;
            }

            $quantity = $record['AvailableQty'];

            $stock = 'onbackorder';

            if ($record['AvailableQty'] >= 1) {
                $stock = 'instock';
            }

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock, $weight, $shipping_class_output, $brand, $quantity, $retail, $price]);
            
        }

        if ($export_url) { 

            // loop through our existing products feed
            foreach ($existing_records as $offset => $existing_record) {

                $sku = $existing_record['SKU'];

                // remove -OER from sku
                $sku = str_replace('-OER', '', $sku);
                $cost = 0;
                $stock = 'outofstock';

                if (!in_array($sku, $current_products)) {
                    // add part to new csv
                    $writer->insertOne([$sku, $cost, $stock]);
                }
            }

        }


        $lines = array();

        // open the processed csv file
        if (($handle = fopen($processed_file, "r")) !== false) {
            // read each line into an array
            while (($data = fgetcsv($handle, 8192, ",")) !== false) {
                // build a "line" from the parsed data
                $line = join(",", $data);

                // if the line has been seen, skip it
                if (isset($lines[$line])) continue;

                // save the line
                $lines[$line] = true;
            }
            fclose($handle);
        }

        // build the new content-data
        $contents = '';
        foreach ($lines as $line => $bool) $contents .= $line . "\r\n";

        $finished_file = 'oer-inventory-update.csv';

        // save it to a new file
        file_put_contents("$dir/$finished_file", $contents);
        
        WP_CLI::success( 'Successfully created ' . $finished_file );
    }

    
    // Goodmark


    public function download_existing_goodmark() {
        $export_url = get_option( '_goodmark_export' );
        
        if ($export_url) {
            $uploads = wp_upload_dir();
            $dir = $uploads['basedir'] . '/vendors/goodmark/';

            // Initialize a file URL to the variable 
            $url = $export_url; 
            
            $fremote = fopen($url, 'rb');
            if (!$fremote) {
                WP_CLI::error( 'There was a problem opening the export url' );
                return false;
            }

            $flocal = fopen($dir . 'goodmark-existing.csv', 'wb');
            if (!$flocal) {
                fclose($fremote);
                WP_CLI::error( 'There was a problem opening local' );
                return false;
            }

            while ($buffer = fread($fremote, 1024)) {
                fwrite($flocal, $buffer);
            }

            WP_CLI::success( 'Successfully written to ' . $dir );
        
            fclose($flocal);
            fclose($fremote);
        } else {
            WP_CLI::error( 'No export URL provided' );
        }
        
    }

    public function download_goodmark() {
        
        // define our files
        $local_file = 'goodmark-temp.csv';
        $server_file = 'RPC_1129552';

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/goodmark/';

        $ftp_server = get_option( '_goodmark_host' );
        $ftp_user_name = get_option( '_goodmark_user' );
        $ftp_user_pass = get_option( '_goodmark_pass' );

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);
        
        // try to download $server_file and save to $local_file
        if (ftp_get($conn_id, $dir.$local_file, $server_file, FTP_BINARY)) {
            // echo "Successfully written to $local_file\n";
            WP_CLI::line( 'Downloading...' );
            WP_CLI::success( 'Successfully written to ' . $dir . $local_file );
        } else {
            WP_CLI::error( 'There was a problem' );
        }

        // close the connection
        ftp_close($conn_id);
    }

    public function process_goodmark() {
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/goodmark/';
        $pre_processed_file = $dir . 'goodmark-temp.csv'; 
        $export_url = get_option( '_goodmark_export' );   

        if ($export_url) { 
         
            $existing_file = $dir . 'goodmark-existing.csv'; 
            $existing_reader = Reader::createFromPath($existing_file, 'r');
            $existing_reader->setHeaderOffset(0);
            $existing_records = $existing_reader->getRecords();

        }
        
        // get file
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // set delimiter
        $reader->setDelimiter('|');

        // ignore header row
        $reader->setHeaderOffset(0);

        // get all the existing records
        $records = $reader->getRecords();

        $processed_file = $dir . 'goodmark-processed.csv';

        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['PartNumber', 'CustomerPrice', 'QuantityAvailable']);

        // array used to compare feed sku vs on site sku
        $current_products = [];

        foreach ($records as $offset => $record) {

            $sku = $record['PartNumber'];
            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);

            array_push( $current_products, $sku );
            
            $cost = $record['CustomerPrice'];

            $stock = 'onbackorder';

            if ($record['QuantityAvailable'] >= 1) {
                $stock = 'instock';
            }
            

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock]);
        }
        
        if ($export_url) { 

            // loop through our existing products feed
            foreach ($existing_records as $offset => $existing_record) {

                $sku = $existing_record['SKU'];
                $cost = 0;
                $stock = 'outofstock';

                if (!in_array($sku, $current_products)) {
                    // add part to new csv
                    $writer->insertOne([$sku, $cost, $stock]);
                }
            }

        }

        $lines = array();

        // open the processed csv file
        if (($handle = fopen($processed_file, "r")) !== false) {
            // read each line into an array
            while (($data = fgetcsv($handle, 8192, ",")) !== false) {
                // build a "line" from the parsed data
                $line = join(",", $data);

                // if the line has been seen, skip it
                if (isset($lines[$line])) continue;

                // save the line
                $lines[$line] = true;
            }
            fclose($handle);
        }

        // build the new content-data
        $contents = '';
        foreach ($lines as $line => $bool) $contents .= $line . "\r\n";

        $finished_file = 'goodmark-inventory-update.csv';

        // save it to a new file
        file_put_contents("$dir/$finished_file", $contents);
        
        WP_CLI::success( 'Successfully created ' . $finished_file );
    }

    // RPUI

    public function download_rpui() {
        $pst = new DateTimeZone('America/Los_Angeles');
        $date = new DateTime('today', $pst);
        $today = $date->format('mdY');
        
        $date->add(DateInterval::createFromDateString('yesterday'));
        $yesterday = $date->format('mdY');
        
        // define our files
        $local_file = 'rpui-temp.csv';

        $server_file = 'InventoryRPUI' . $today . '.csv';
        $yesterday_server_file = 'InventoryRPUI' . $yesterday . '.csv';

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/rpui/';

        $ftp_server = get_option( '_general_host' );
        $ftp_user_name = get_option( '_general_user' );
        $ftp_user_pass = get_option( '_general_pass' );

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        // try to download $server_file and save to $local_file
        if (ftp_get($conn_id, $dir.$local_file, $server_file, FTP_BINARY)) {
            // echo "Successfully written to $local_file\n";
            WP_CLI::line( 'Downloading...' );
            WP_CLI::success( 'Successfully written to ' . $dir . $local_file );

            WP_CLI::line( 'Deleting yesterdays file...' );
            if (ftp_delete($conn_id, $yesterday_server_file)) {
                WP_CLI::success( 'Successfully deleted ' . $yesterday_server_file );
               } else {
                WP_CLI::error( 'Could not delete ' . $yesterday_server_file );
            }

        } else {
            WP_CLI::error( 'There was a problem downloading RPUI feed file' );
        }

        // close the connection
        ftp_close($conn_id);
    }

    public function process_rpui() {
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/rpui/';
        $pre_processed_file = $dir . 'rpui-temp.csv';    
        
        // get file
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // ignore header row
        $reader->setHeaderOffset(0);

        // get all the existing records
        $records = $reader->getRecords();

        $processed_file = $dir . 'rpui-processed.csv';

        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['Brand', 'PartNumber', 'QuantityAvailable']);

        foreach ($records as $offset => $record) {

            $brand = $record['brand'];
            
            $sku = $record['sku'];
            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);
    
            $stock = $record['qty'];

            // add part to new csv
            $writer->insertOne([$brand, $sku, $stock]);
        }

        $lines = array();

        // open the processed csv file
        if (($handle = fopen($processed_file, "r")) !== false) {
            // read each line into an array
            while (($data = fgetcsv($handle, 8192, ",")) !== false) {
                // build a "line" from the parsed data
                $line = join(",", $data);

                // if the line has been seen, skip it
                if (isset($lines[$line])) continue;

                // save the line
                $lines[$line] = true;
            }
            fclose($handle);
        }

        // build the new content-data
        $contents = '';
        foreach ($lines as $line => $bool) $contents .= $line . "\r\n";

        $finished_file = 'rpui-inventory-update.csv';

        // save it to a new file
        file_put_contents("$dir/$finished_file", $contents);
        
        WP_CLI::success( 'Successfully created ' . $finished_file );
    }

    // Sherman

    public function download_sherman() {
        
        // define our files
        $local_file = 'sherman-temp.csv';
        $server_file = 'Meyer Inventory.csv';

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/sherman/';

        $ftp_server = get_option( '_general_host' );
        $ftp_user_name = get_option( '_general_user' );
        $ftp_user_pass = get_option( '_general_pass' );

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($conn_id, true);

        // try to download $server_file and save to $local_file
        if (ftp_get($conn_id, $dir.$local_file, $server_file, FTP_BINARY)) {
            // echo "Successfully written to $local_file\n";
            WP_CLI::line( 'Downloading...' );
            WP_CLI::success( 'Successfully written to ' . $dir . $local_file );
        } else {
            WP_CLI::error( 'There was a problem' );
        }

        // close the connection
        ftp_close($conn_id);
    }

   

    public function process_sherman() {

        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/sherman/';
        $pre_processed_file = $dir . 'sherman-temp.csv';    

        // temp pricing file
        $pricing_file = $dir . 'sherman-pricing.csv';

        $current_products = ['sku', 'price'];

        // process pricing file
        if ($pricing_file) { 

            //$current_products = array_map('str_getcsv', file($pricing_file));
         
            $pricing_reader = Reader::createFromPath($pricing_file, 'r');
            $pricing_reader->setHeaderOffset(0);
            $pricing_records = $pricing_reader->getRecords();

            foreach ($pricing_records as $offset => $pricing_record) {
                
                $sku = $pricing_record['SKU'];
                $price = $pricing_record['PRICE'];

                $current_products += array($sku => $price);
            }

        }

        // get file
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // ignore header row
        $reader->setHeaderOffset(0);

        // get all the existing records
        $records = $reader->getRecords();

        $processed_file = $dir . 'sherman-processed.csv';

        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['PartNumber', 'QuantityAvailable','price']);

        

        foreach ($records as $offset => $record) {
            $brand = $record['MFGName'];
            

            if ($brand == 'Sherman Parts') {

                $sku = $record['MFG Item Number'];
                $stock = $record['Available'];

                if (array_key_exists($sku, $current_products)) {
                    $writer->insertOne([$sku, $stock, $current_products[$sku]]);
                }

                
                
            }
            
        }

        $lines = array();

        // open the processed csv file
        if (($handle = fopen($processed_file, "r")) !== false) {
            // read each line into an array
            while (($data = fgetcsv($handle, 8192, ",")) !== false) {
                // build a "line" from the parsed data
                $line = join(",", $data);

                // if the line has been seen, skip it
                if (isset($lines[$line])) continue;

                // save the line
                $lines[$line] = true;
            }
            fclose($handle);
        }

        // build the new content-data
        $contents = '';
        foreach ($lines as $line => $bool) $contents .= $line . "\r\n";

        $finished_file = 'sherman-inventory-update.csv';

        // save it to a new file
        file_put_contents("$dir/$finished_file", $contents);
        
        WP_CLI::success( 'Successfully created ' . $finished_file );
    }

}

function rp_cli_register_commands() {
	WP_CLI::add_command( 'rp', 'RP_CLI' );
}

add_action( 'cli_init', 'rp_cli_register_commands' );