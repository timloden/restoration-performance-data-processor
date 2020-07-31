<?php
/**
 * Plugin Name:     Restoration Performance Data Processor
 * Plugin URI:      https://restorationperformance.com
 * Description:     Plugin for processing vendor data to update stock and pricing
 * Author:          Tim Loden
 * Author URI:      https://timloden.com
 * Text Domain:     restoration-performance-data-processor
 * Domain Path:     /languages
 * Version:         0.1.4
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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\CannotInsertRecord;

class RP_CLI {

	public function download_oer() {
        
        // define our files
        $local_file = 'oer-temp.csv';
        $server_file = 'Restoration_Performance.csv';

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/vendors/oer/';

        // ftp 
        // $ftp_server = "ds8874.dreamservers.com";
        // $ftp_user_name = "rpc_uploads";
        // $ftp_user_pass = "s3R-tjhM";

        $ftp_server = $_ENV['GENERAL_SERVER'];
        $ftp_user_name = $_ENV['GENERAL_SERVER_USERNAME'];
        $ftp_user_pass = $_ENV['GENERAL_SERVER_PASSWORD'];

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

}

function rp_cli_register_commands() {
	WP_CLI::add_command( 'rp', 'RP_CLI' );
}

add_action( 'cli_init', 'rp_cli_register_commands' );