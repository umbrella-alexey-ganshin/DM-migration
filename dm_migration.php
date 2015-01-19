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
     * --name
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
     * @synopsis <backup_db_name> <copy_db_name> <db_password> [--username=<user_name>] [--host=<host>] [--port=<port>] [--chroot=<chroot>]
     */
    public function copydb( $args, $assoc_args ) {
        list( $backup_db_name, $copy_db_name, $db_password ) = $args;
        $user_name = isset( $assoc_args['username'] ) ? $assoc_args['username'] : 'root';
        $shell_command = sprintf( 'sudo mysqlhotcopy %s %s --user=%s --password=%s', $backup_db_name, $copy_db_name, $user_name, $db_password );
        

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
}

WP_CLI::add_command( 'dm_migration', 'DailyMakeover_Migration' );
