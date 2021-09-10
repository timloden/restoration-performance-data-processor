<?php
/**
 * Plugin Name:     Restoration Performance Data Processor
 * Plugin URI:      https://restorationperformance.com
 * Description:     Plugin for processing vendor data to update stock and pricing
 * Author:          Tim Loden
 * Author URI:      https://timloden.com
 * Text Domain:     restoration-performance-data-processor
 * Domain Path:     /languages
 * Version:         1.6.2
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
            Field::make( 'text', 'general_host', 'General Hostname' ),
            Field::make( 'text', 'general_user', 'General Username' ),
            Field::make( 'text', 'general_pass', 'General Password' ),
            Field::make( 'separator', 'crb_goodmark_separator', __( 'Goodmark FTP' ) ),
            Field::make( 'text', 'goodmark_host', 'Goodmark Hostname' ),
            Field::make( 'text', 'goodmark_user', 'Goodmark Username' ),
            Field::make( 'text', 'goodmark_pass', 'Goodmark Password' ),
            Field::make( 'separator', 'crb_oer_separator', __( 'OER' ) ),
            Field::make( 'text', 'oer_export', 'OER Export URL' ),
            Field::make( 'text', 'oer_file_name', 'OER File Name' ),
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

        $to = 'tloden@restorationperformance.com';
        $subject = get_bloginfo( 'name' ) . ' Backorders - ' . date("m/d/Y");
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $body, $headers );
    }

    // Dynacorn

    public function download_dynacorn() {
        
        // define our files
        $local_file = 'dynacorn-temp.xls';
        $server_file = 'DynacornInventory.xls';

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
        } else {
            WP_CLI::error( 'There was a problem' );
        }

        // close the connection
        ftp_close($conn_id);
    }

    public function process_dynacorn() {
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/dynacorn/';
        $pre_processed_file = $dir . 'dynacorn-temp.xls';
        $finished_file = 'dynacorn-inventory-update.csv';    
        
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($pre_processed_file);
        $spreadsheet = $reader->load($pre_processed_file);

        //print_r($spreadsheet);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        $writer->save($dir . $finished_file);

        WP_CLI::success( 'Successfully created ' . $finished_file );
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
        $writer->insertOne(['PartNumber', 'Cost', 'AvailableQty']);

        // array used to compare feed sku vs on site sku
        $current_products = [];

        // loop through the OER feed
        foreach ($records as $offset => $record) {

            $sku = $record['PartNumber'];

            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);

            array_push( $current_products, $sku );
            
            $cost = $record['Cost'];
            $stock = $record['AvailableQty'];

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock]);
        }

        if ($export_url) { 

            // loop through our existing products feed
            foreach ($existing_records as $offset => $existing_record) {

                $sku = $existing_record['SKU'];

                // remove -OER from sku
                $sku = str_replace('-OER', '', $sku);
                $cost = 0;
                $stock = 0;

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
            $stock = $record['QuantityAvailable'];
            

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock]);
        }
        
        if ($export_url) { 

            // loop through our existing products feed
            foreach ($existing_records as $offset => $existing_record) {

                $sku = $existing_record['SKU'];
                $cost = 0;
                $stock = 0;

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