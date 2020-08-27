<?php
/**
 * Plugin Name:     Restoration Performance Data Processor
 * Plugin URI:      https://restorationperformance.com
 * Description:     Plugin for processing vendor data to update stock and pricing
 * Author:          Tim Loden
 * Author URI:      https://timloden.com
 * Text Domain:     restoration-performance-data-processor
 * Domain Path:     /languages
 * Version:         1.0.0
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
        ) );
}
add_action( 'carbon_fields_register_fields', 'dbi_add_plugin_settings_page' );

// WP CLI Commands

class RP_CLI {

	public function download_oer() {
        
        // define our files
        $local_file = 'oer-temp.csv';
        $server_file = 'Restoration_Performance.csv';

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
        
        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/oer/';
        $pre_processed_file = $dir . 'oer-temp.csv';    
        
        // get file
        $reader = Reader::createFromPath($pre_processed_file, 'r');

        // ignore header row
        $reader->setHeaderOffset(0);

        // get all the existing records
        $records = $reader->getRecords();

        $processed_file = $dir . 'oer-processed.csv';

        // add our writer for output
        $writer = Writer::createFromPath($processed_file, 'w+');

        // add our header
        $writer->insertOne(['PartNumber', 'Cost', 'AvailableQty']);

        foreach ($records as $offset => $record) {

            $sku = $record['PartNumber'];
            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);
            
            $cost = $record['Cost'];
            $stock = $record['AvailableQty'];

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock]);
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

        foreach ($records as $offset => $record) {

            $sku = $record['PartNumber'];
            // remove asterisks from part number
            $sku = preg_replace('/[\*]+/', '', $sku);
            
            $cost = $record['CustomerPrice'];
            $stock = $record['QuantityAvailable'];

            // add part to new csv
            $writer->insertOne([$sku, $cost, $stock]);
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

        $date = new DateTime();
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
    

}

function rp_cli_register_commands() {
	WP_CLI::add_command( 'rp', 'RP_CLI' );
}

add_action( 'cli_init', 'rp_cli_register_commands' );