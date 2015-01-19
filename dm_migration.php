<?php

/*
 * Write "wp help dm_migration <SUBCOMMAND>" for read more
 */
class DailyMakeover_Migration extends WP_CLI_Command {
    /**
     *
     * ## OPTIONS
     *
     * ## EXAMPLES
     *
     * @synopsis
     */
    public function import( $args, $assoc_args ) {
        WP_CLI::line( 'HELLO WORLD!!!!!!!' );
    }

    /**
     * Creates a copy of backup DB 
     *
     * ## OPTIONS
     *
     * --username
     * : The MySQL user name to use when connecting to the server.
     * 
     * --port
     * : The TCP/IP port number to use when connecting to the local server.
     * 
     * --chroot
     * : Base directory of the chroot jail in which mysqld operates. The path value should match that of the --chroot option given to mysqld.
     * 
     * --host
     * : The host name of the local host to use for making a TCP/IP connection to the local server. By default, the connection is made to localhost using a Unix socket file.
     * 
     * ## EXAMPLES
     * 
     * wp --require=dm_migration.php dm_migration copydb UserProducts UserProducts1 12345 --username=user --port=8080 --chroot=/home/ --host=localhost
     *
     * @synopsis <backup_db_name> <copy_db_name> <db_password> [--username=<username>] [--host=<host>] [--port=<port>] [--chroot=<chroot>]
     */
    public function copydb( $args, $assoc_args ) {
        list( $backup_db_name, $copy_db_name, $db_password ) = $args;
        $user_name = isset( $assoc_args['username'] ) ? $assoc_args['username'] : 'root';
        $shell_command = sprintf( 'sudo mysqlhotcopy %s %s --allowold --user=%s --password=%s', $backup_db_name, $copy_db_name, $user_name, $db_password );

        if ( isset( $assoc_args['host'] ) ) {
            $shell_command .= sprintf( ' --host=%s', $assoc_args['host'] );
        }
        if ( isset( $assoc_args['port'] ) ) {
            $shell_command .= sprintf( ' --port=%s', $assoc_args['port'] );
        }
        if ( isset( $assoc_args['chroot'] ) ) {
            $shell_command .= sprintf( ' --chroot=%s', $assoc_args['chroot'] );
        }
        
        WP_CLI::line( shell_exec( $shell_command ) );
    }

    /**
     * Creates a copy of backup DB
     *
     * ## OPTIONS
     * 
     * --filepath
     * : Output file path
     *
     * ## EXAMPLES
     *
     * wp --require=dm_migration.php dm_migration make_hairstyle_csv hairstyle.csv
     *
     * @synopsis [--filepath=<filepath>]
     */
    public function make_hairstyle_csv( $args, $assoc_args ) {
        $filepath = ( isset( $assoc_args['filepath'] ) ) ? $assoc_args['filepath'] : 'hairstyles.csv';
        $filestream = fopen( $filepath, 'w' );
        
        $hairstyle_terms = get_terms( 'hairstyle_category' );
        foreach( $hairstyle_terms as $term ) {
            $term_new_name = $term->name;
            if ( strpos( $term_new_name, ' Hairstyles' ) ) {
                $term_new_name = str_replace( ' Hairstyles', '', $term_new_name );
            }
            
            $term_csv_representation = array( $term->term_id, $term->name, '', '', '', $term_new_name );
            
            fputcsv( $filestream, $term_csv_representation );
            
            WP_CLI::success( sprintf( 'Term with "%s" ID, "%s" name. Renamed to "%s".', $term->term_id, $term->name, $term_new_name ) );
        }
        
        fclose( $filestream );

        WP_CLI::success( 'Done without errors' );
    }
}

WP_CLI::add_command( 'dm_migration', 'DailyMakeover_Migration' );
