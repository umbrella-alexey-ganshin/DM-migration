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
        
        $hairstyle_terms = get_terms( 'hairstyle_category', array('hide_empty' => false) );
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

    /**
     * Makes a CSV file with list of users that has a posts
     *
     * ## OPTIONS
     *
     * --number
     * : Count of the users to query
     * 
     * --path
     * : Path to output a CSV
     * 
     * ## EXAMPLES
     *
     * wp --require=dm_migration.php dm_migration get_array_of_users_with_posts 200 --path=/home/user/
     *
     * @synopsis [<number>] [--pathtofile=<path>]
     */
    public function make_users_csv( $args, $assoc_args ) {
        $users = get_users( array( 'number' => isset( $args[0] ) ? $args[0] : '' ) );
        
        $post_type_arg = $this->get_blogs_post_types_names();
        $post_type_arg = array_merge( $post_type_arg, array(
            'post',
            'option-tree',
            'beauty-tips',
            'bloggerati',
            'makeovergallery',
            'mobile_app',
            'hairstyles',
            'mobilegalleryimage'
        ) );

        $users_filepath = ( isset( $assoc_args['pathtofile'] ) ) ? $assoc_args['pathtofile'] . 'users_with_posts.csv' : 'users_with_posts.csv';
        $users_meta_filepath = ( isset( $assoc_args['pathtofile'] ) ) ? $assoc_args['pathtofile'] . 'users_with_posts_meta.csv' : 'users_with_posts_meta.csv';
        
        $users_filestream = fopen( $users_filepath, 'w' );
        $users_meta_filestream = fopen( $users_meta_filepath, 'w' );
        
        $added_users_count = 0;
        
        foreach( $users as $user ) {
            $args = array(
                'author'       => $user->ID,
                'hide_empty'   => false,
                'post_status'  => 'any',
                'post_type'    => $post_type_arg
            );
            $posts_query = new WP_Query( $args );
            wp_reset_query();
            
            if ( ! empty( $posts_query->posts ) ) {
                $user_meta = get_user_meta( $user->ID );
                
                fputcsv( $users_filestream, array(
                    $user->ID,
                    $user->user_login,
                    $user->user_email,
                    $user->first_name,
                    $user->last_name
                ) );

//                fputcsv( $users_meta_filestream, $user_meta );

                WP_CLI::success( sprintf( 'User "%s" with %s posts has been added', $user->user_nicename, count( $posts_query->posts ) ) );

                $added_users_count++;
            }
        }

        WP_CLI::success( sprintf( '%s users has been added',  count( $added_users_count ) ) );
    }

    /**
     * Makes a CSV file with list of hairtyles dates
     *
     * ## OPTIONS
     *
     * --path
     * : Path to output a CSV
     *
     * ## EXAMPLES
     *
     * wp --require=dm_migration.php dm_migration make_hairstyles_dates_csv --path=/home/user/
     *
     * @synopsis [--path=<path>]
     */
    public function make_hairstyles_dates_csv( $args, $assoc_args ) {
        $filepath = ( isset( $assoc_args['path'] ) ) ? $assoc_args['path'] : 'hairstyles_dates.csv';
        $filestream = fopen( $filepath, 'w' );
        
        $filewitherrors = fopen( 'hairstyles_dates_errors.csv', 'w' );

        $posts_query = new WP_Query( array(
            'post_type'      => 'hairstyles',
            'post_status'    => 'any',
            'posts_per_page' => -1
        ) );
        
        $succeded_posts = 0;

        while( $posts_query->have_posts() ) {
            $posts_query->the_post();
            $post = get_post();
            
            $date_substr_start = strrpos( $post->post_title, '(' ) + 1;
            $date_substr_length = strrpos( $post->post_title, ')' ) - $date_substr_start;
            $date_string = trim( substr( $post->post_title, $date_substr_start, $date_substr_length ) );
            
            $date = DateTime::createFromFormat( 'M j, Y', $date_string );

            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'M Y', $date_string );
            }
            
            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'M j Y', $date_string );
            }

            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'F j, Y', $date_string );
            }

            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'F j Y', $date_string );
            }

            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'F jS, Y', $date_string );
            }

            if ( ! $date ) {
                $date = DateTime::createFromFormat( 'F jS Y', $date_string );
            }
            
            if ( ! $date ) {
                WP_CLI::warning( sprintf( 'Can\'t process the "%s" date format', $date_string ) );

                fputcsv( $filewitherrors, array( $post->ID, $post->post_title, '', '', '' ) );
                
                continue;
            }

            $day   = $date->format( 'd' );
            $month = $date->format( 'm' );
            $year  = $date->format( 'Y' );
            
            if ( $year == '0006' ) {
                var_dump( $date_string . ' ' . $date->format( 'm d Y' )  );
                die();
            }
            
            fputcsv( $filestream, array( $post->ID, $post->post_title, $day, $month, $year ) );

            WP_CLI::success( sprintf( 'Added post with "%s" ID, "%s" day, "%s" month, %s year', $post->ID, $day, $month, $year ) );
            $succeded_posts++;
        }
        
        wp_reset_query();
        fclose( $filestream );
        WP_CLI::success( sprintf( 'Done without errors. Succeded %s%% posts. %s dates has not been added because of the date format' , ( $succeded_posts / $posts_query->post_count ) * 100, $posts_query->post_count - $succeded_posts ) );
    }

    /**
     * @return array
     */
    private function get_blogs_post_types_names() {
        $blogs = array(
            'Backstage Beauty',
            'Beauty On A Dime',
            'Beauty Street',
            'Fall Trend Watch',
            'Holiday Feature',
            'HUEman Behavior',
            'In His Beauty Universe',
            'Now That\'s A Makeover',
            'Positively Beautiful',
            'Press Room',
            'Summer of Color',
            'These Lips are Made for Glossin',
            'Wedding Beauty'
        );
        
        $post_types_names = array();
        
        foreach ( $blogs as $blog ) {
            $lowercase = str_replace( "'", '', strtolower( $blog ) );
            $posttypename = str_replace( ' ', '-', $lowercase );

            if ( $blog == 'These Lips are Made for Glossin' ) {
                $posttypename = 'lips-for-glossin';
            }

            if ( $blog == 'In His Beauty Universe' ) {
                $posttypename = 'his-beauty-universe';
            }

            if ( $blog == 'Backstage Beauty' ) {
                $posttypename = 'fashion-week';
            }

            $post_types_names[] = $posttypename;
        }
        
        return $post_types_names;
    }
}

WP_CLI::add_command( 'dm_migration', 'DailyMakeover_Migration' );
