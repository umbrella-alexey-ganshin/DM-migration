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
        
        $users_filestream = fopen( $users_filepath, 'w' );
        
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
                fputcsv( $users_filestream, array(
                    $user->ID,
                    $user->user_login,
                    $user->user_email,
                    $user->first_name,
                    $user->last_name
                ) );

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
     * Makes a XML file with list of hairtyles for wordpress importing
     *
     * ## OPTIONS
     *
     * --xmlfilename
     * : File name of an output XML. Default is "wordpress_hairstyles.xml"
     *
     * ## EXAMPLES
     *
     * wp --require=dm_migration.php dm_migration make_hairstyles_xml --xmlfilename=/home/user/file.xml
     *
     * @synopsis <inputfile> [--outputfile=<filename>] [--termscsvpath=<termscsvpath>] [--datescsvpath=<datescsvpath>]
     */
    public function make_hairstyles_xml( $args, $assoc_args ) {
        list( $inputfile ) = $args;
        $outputfile_path = ( isset( $assoc_args['outputfile'] ) ) ? $assoc_args['outputfile'] : 'wordpress_hairstyles.xml';
        $terms_csv_path  = ( isset( $assoc_args['termscsvpath'] ) ) ? $assoc_args['termscsvpath'] : 'hairstyles.csv';
        $dates_csv_path  = ( isset( $assoc_args['datescsvpath'] ) ) ? $assoc_args['datescsvpath'] : 'hairstyles_dates.csv';
        
        $xml = new DOMDocument();
        $xml->load( $inputfile );

        $terms_csv_stream = fopen( $terms_csv_path, 'r' );
        $dates_csv_stream = fopen( $dates_csv_path, 'r' );
        
        //Initialize associative array of terms
        $terms_array = array();
        while ( ! feof( $terms_csv_stream ) ) {
            $fields = fgetcsv( $terms_csv_stream );
            $terms_array[ $fields[0] ] = $fields;
        }

        fclose( $terms_csv_stream );
        
        //Initialize assocoative array of dates
        $dates_array = array();
        while ( ! feof( $dates_csv_stream ) ) {
            $fields = fgetcsv( $dates_csv_stream );
            $dates_array[ $fields[0] ] = $fields;
        }
        
        fclose( $dates_csv_stream );
        
        $warnings_count = 0;
        
        //Get the generator DOM for terms insertion before him (will be after authors)
        $generator_dom = $xml->getElementsByTagName( 'generator' )->item( 0 );
        
        $add_term_definition = function( $document, $generator, $id, $taxonomy, $slug, $parent_slug, $name ) {
            //Define the term_id DOM
            $term_definition_id_dom = $document->createElement( 'wp:term_id', $id );

            //Define the term_taxonomy DOM
            $term_definition_taxonomy_dom = $document->createElement( 'wp:term_taxonomy', $taxonomy );

            //Define the term_slug DOM
            $term_definition_slug_dom = $document->createElement( 'wp:term_slug', $slug ); //Generate slug from the new name

            //Define the term_parent DOM
            $term_definition_parent_dom = $document->createElement( 'wp:term_parent', $parent_slug );

            //Define the term_name DOM
            $cdata_term_name_section = new DOMCdataSection( $name );
            $term_definition_name_dom = $document->createElement( 'wp:term_name' );
            //Add CDATA section to term_name
            $term_definition_name_dom->appendChild( $cdata_term_name_section );

            //Add all definitions to the main DOM
            $term_definition_main_dom = $document->createElement( 'wp:term' );
            $term_definition_main_dom->appendChild( $term_definition_id_dom );
            $term_definition_main_dom->appendChild( $term_definition_taxonomy_dom );
            $term_definition_main_dom->appendChild( $term_definition_slug_dom );
            $term_definition_main_dom->appendChild( $term_definition_parent_dom );
            $term_definition_main_dom->appendChild( $term_definition_name_dom );

            $generator->parentNode->insertBefore( $term_definition_main_dom, $generator );
        };
        
        $get_not_date_term_taxonomy = function( $is_celebrity, $is_style, $is_event ) {
            switch ( true ) {
                case $is_celebrity :
                    return 'celebrity_hairstyle_category';
                case $is_style :
                    return 'style_hairstyle_category';
                case $is_event :
                    return 'event_hairstyle_category';
            }
            return '';
        };
        
        $terms_id_by_slug = array();
        
        //Add terms definitions in XML after authors (without dates)
        foreach( $terms_array as $term_id => $term_fields_array ) {
            list( $csv_term_id, $csv_term_oldname, $csv_term_iscelebrity, $csv_term_isstyle, $csv_term_isevent, $csv_term_newname ) = $term_fields_array;
            
            //Get taxonomy of the term
            $term_taxonomy = $get_not_date_term_taxonomy( $get_not_date_term_taxonomy, $csv_term_isstyle, $csv_term_isevent );

            $slug = sanitize_title_with_dashes( $csv_term_newname );
            $add_term_definition( $xml, $generator_dom, $csv_term_id, $term_taxonomy, sanitize_title_with_dashes( $csv_term_newname ), '', $csv_term_newname );
            $terms_id_by_slug[ $slug ] = $term_id;
            
            WP_CLI::success( sprintf( 'Term with %s id has successfully added to the definitions', $csv_term_id ) );
        }

        $get_day_parent = function( $used_months_slugs, $month, $year ) {
            if ( in_array( $month . '-' . $year, $used_months_slugs ) ) {
                return sanitize_title_with_dashes( $month . '-' . $year );
            }
            if ( in_array( $month, $used_months_slugs ) ) {
                return sanitize_title_with_dashes( $month );
            }
            return null;
        };
        
        $used_days_slugs   = array();
        $used_months_slugs = array();
        $used_years_slugs  = array();
        
        //Add dates terms definitions right before the generator DOM
        foreach ( $dates_array as $date_id => $date_fields_array ) {
            list( $csv_post_id, $csv_post_title, $csv_day, $csv_month, $csv_year ) = $date_fields_array;
            
            //Process the year
            if ( ! in_array( $csv_year, $used_years_slugs ) ) {
                $add_term_definition( $xml, $generator_dom, '', 'date_hairstyle_category', sanitize_title_with_dashes( $csv_year ), '', $csv_year );
                $used_years_slugs[] = $csv_year;

                WP_CLI::success( sprintf( 'Year term with %s slug has successfully added to the definitions', $csv_year ) );
            }
            
            //Process the month. Slug can be like 04-2010 or just 04
            if ( in_array( $csv_month, $used_months_slugs ) && ! in_array( $csv_month . '-' . $csv_year, $used_months_slugs ) ) {
                $month_slug = $csv_month . '-' . $csv_year;
                $add_term_definition( $xml, $generator_dom, '', 'date_hairstyle_category', sanitize_title_with_dashes( $month_slug ), $csv_year, $csv_month );
                $used_months_slugs[] = $month_slug;

                WP_CLI::success( sprintf( 'Month term with %s slug has successfully added to the definitions', $month_slug ) );
            }
            if ( ! in_array( $csv_month, $used_months_slugs ) ) {
                $add_term_definition( $xml, $generator_dom, '', 'date_hairstyle_category', sanitize_title_with_dashes( $csv_month ), $csv_year, $csv_month );
                $used_months_slugs[] = $csv_month;

                WP_CLI::success( sprintf( 'Month term with %s slug has successfully added to the definitions', $csv_month ) );
            }
            
            //Process the day. Slug can be like 01-01-2010 or just 01
            if ( in_array( $csv_day, $used_days_slugs ) && ! in_array( $csv_day . '-' . $csv_month . '-' . $csv_year, $used_days_slugs ) ) {
                $day_slug = $csv_day . '-' . $csv_month . '-' . $csv_year;
                $day_parent_slug = $get_day_parent( $used_months_slugs, $csv_month, $csv_year );
                
                if ( ! $day_parent_slug ) {
                    WP_CLI::warning( sprintf( 'Can\'t get the parent slug of day with %s slug', $day_slug ) );
                    $warnings_count++;
                }
                
                $add_term_definition( $xml, $generator_dom, '', 'date_hairstyle_category', sanitize_title_with_dashes( $day_slug ), $day_parent_slug, $csv_day );
                $used_days_slugs[] = $day_slug;

                WP_CLI::success( sprintf( 'Day term with %s slug has successfully added to the definitions', $day_slug ) );
            }
            if ( ! in_array( $csv_day, $used_days_slugs ) ) {
                $day_parent_slug = $get_day_parent( $used_months_slugs, $csv_month, $csv_year );

                if ( ! $day_parent_slug ) {
                    WP_CLI::warning( sprintf( 'Can\'t get the parent slug has of day with %s slug', $csv_day ) );
                    $warnings_count++;
                }
                
                $add_term_definition( $xml, $generator_dom, '', 'date_hairstyle_category', sanitize_title_with_dashes( $csv_day ), $day_parent_slug, $csv_day );
                $used_days_slugs[] = $csv_day;

                WP_CLI::success( sprintf( 'Day term with %s slug has successfully added to the definitions', $csv_day ) );
            }
        }
        
        $set_term_dom = function( $term_dom, $taxonomy, $slug, $name ) {
            $term_dom->setAttribute( 'domain', $taxonomy );
            $term_dom->setAttribute( 'nicename', sanitize_title_with_dashes( $slug ) );

            if ( $term_dom->firstChild ) {
                //Remove old CDATA
                $term_dom->removeChild($term_dom->firstChild);
            }
            $cdata_term_name_section = new DOMCdataSection( $name );
            $term_dom->appendChild( $cdata_term_name_section );
            
            return $term_dom;
        };

        //Add terms to the posts
        $posts_doms = $xml->getElementsByTagName( 'item' );
        foreach ( $posts_doms as $post_dom ) {
            $post_id = $post_dom->getElementsByTagName( 'post_id' )->item( 0 )->nodeValue;
            
            //Edit existing terms to new
            $terms_doms = $post_dom->getElementsByTagName( 'category' );
            foreach( $terms_doms as $term_dom ) {
                $term_name = $term_dom->textContent;
                $term_new_name = str_replace( ' Hairstyles', '', $term_name );
                
                $term_slug = sanitize_title_with_dashes( $term_new_name );
                $term_id = $terms_id_by_slug[ $term_slug ];

                list( $csv_term_id, $csv_term_oldname, $csv_term_iscelebrity, $csv_term_isstyle, $csv_term_isevent, $csv_term_newname ) = $terms_array[ $term_id ];

                $taxonomy = $get_not_date_term_taxonomy( $csv_term_iscelebrity, $csv_term_isstyle, $csv_term_isevent );

                $set_term_dom( $term_dom, $taxonomy, $term_slug, $term_new_name );
            }

            //Generate date term for the post
            list( $csv_post_id, $csv_post_title, $csv_day, $csv_month, $csv_year ) = $dates_array[ $post_id ];
            
            //Get day slug
            $day_slug = $csv_day . '-' . $csv_month . '-' . $csv_year;
            if ( ! in_array( $day_slug, $used_days_slugs ) ) {
                $day_slug = $csv_day;
            }
            
            //Get month slug
            $month_slug = $csv_month . '-' . $csv_year;
            if ( ! in_array( $month_slug, $used_months_slugs ) ) {
                $month_slug = $csv_month;
            }
            
            //Get year slug
            $year_slug = $csv_year;
            
            //Generate day term
            $post_day_term_dom = $xml->createElement( 'category' );
            $post_day_term_dom = $set_term_dom( $post_day_term_dom, 'date_hairstyle_category', $day_slug, $csv_day );
            $post_dom->appendChild( $post_day_term_dom );
            
            //Generate month term
            $post_month_term_dom = $xml->createElement( 'category' );
            $post_month_term_dom = $set_term_dom( $post_month_term_dom, 'date_hairstyle_category', $month_slug, $csv_month );
            $post_dom->appendChild( $post_month_term_dom );

            //Generate year term
            $post_year_term_dom = $xml->createElement( 'category' );
            $post_year_term_dom = $set_term_dom( $post_year_term_dom, 'date_hairstyle_category', $year_slug, $csv_year );
            $post_dom->appendChild( $post_year_term_dom );
            
            //Set new post type
            $post_type_dom = $post_dom->getElementsByTagName( 'post_type' )->item( 0 );
            $post_type_dom->nodeValue = 'celebrity_hairstyle';
        }
        
        $xml->save( $outputfile_path );
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
