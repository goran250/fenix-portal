<?php

    /**
     * Shows list of members for a given event who are either cancelled (avanmälda)
     * or registered (föranmälda) depending on querystring parameters.
     * Expected querystring: ?event_id=123&status=avanmalan  OR  ?event_id=123&status=foranmalan
     * Swedish synonyms are accepted for status: 'avan', 'foran', 'avanmalda', 'foranmalda', etc.
     */

    function shortcode_applications( $atts ) {
        // Only allow administrators to view the signups list
        if ( ! current_user_can( 'manage_options' ) ) {
            return '<div style="color: green;"><strong>Du måste vara administratör för att se anmälningar.</strong></div>';
        }

        $event_id = isset( $_GET['event_id'] ) ? intval( wp_unslash( $_GET['event_id'] ) ) : 0;
        $event_status_raw = isset( $_GET['event_status'] ) ? sanitize_text_field( wp_unslash( $_GET['event_status'] ) ) : '';
        $event_status = strtolower( trim( $event_status_raw ) );

        if ( $event_status !== 'avanmalan' && $event_status !== 'foranmalan' ) {
            return '<div>Ogiltigt statusvärde.</div>';
        }

        if ( $event_id <= 0 ) {
            return '<div>Event-id saknas eller ogiltigt.</div>';
        }

        $event = get_post( $event_id );
        if ( ! $event || $event->post_type !== 'event' ) {
            return '<div>Eventet hittades inte.</div>';
        }
        
        $event->event_date = get_post_meta( $event->ID, 'event_date', true );

        if ( $event_status === 'avanmalan' ) {
            $user_ids = get_post_meta( $event->ID, 'event_cancellations', true );
            $heading = 'Avanmälningar';
        } else {
            $user_ids = get_post_meta( $event->ID, 'event_registrations', true );
            $heading = 'Föranmälningar';
        }

        if ( ! is_array( $user_ids ) ) {
            $user_ids = array();
        }
        
        $event->event_date = get_post_meta( $event->ID, 'event_date', true );

        $out = '<div class="applications-list">';

        if ( !empty( $user_ids ) ) {
            $out .= '<div>';        
            $out .= '   <h4 style="margin-bottom: 5px;">' . 
                            esc_html( $heading ) . ' för ' . esc_html( $event->post_title ) . ' den ' . esc_html( date_i18n( 'j F Y', strtotime( $event->event_date ) ) ) . 
                        '</h4>';
            
            foreach ( $user_ids as $uid ) {
                $user = get_userdata( intval( $uid ) );
                if ( ! $user ) 
                    continue;

                $out .= '<div class="member-info">' . esc_html($user->display_name) . '&nbsp; - &nbsp;' . esc_html(get_user_meta( $user->ID, 'company', true )) . '</div>';
            }
            $out .= '</div>'; 
        }
        else {
            $out .= '<div>'; 
                if ( $event_status === 'avanmalan' ) { 
                    $out .= '<h4><strong>Inga avanmälda medlemmar hittades.</strong></h4>';
                } else {
                    $out .= '<h4><strong>Inga föranmälda medlemmar hittades.</strong></h4>';
                }
            $out .= '</div>';
        } 
        
        // Hämtar alla  inbjudna gäster och medlemskapsgäster för ett visst event.
        $invited_guests = get_posts(array(
            'post_type'  => 'guest',
            'post_status' => 'publish',
            'post_parent' => $event->ID,   // exact title match (WP support varies by version)
        ));

        $membership_guests = array();        

        if ( ! empty( $invited_guests ) ) {
            // Flytta membership-guests till $membership_guests array.
            for ( $i = 0; $i < count($invited_guests); $i++ ) {
                $guest = $invited_guests[$i];
                $is_membership_guest = get_post_meta($guest->ID, 'is_membership_guest', true);
                if ($is_membership_guest == "Yes") {                   
                    array_splice($invited_guests, $i, 1);
                    array_push($membership_guests, $guest);
                    $i--; // Adjust index after removal to avoid skipping the next element
                }
            }

            if ( ! empty( $invited_guests ) ) {
                $out .= '<div class="invited-guests">';
                $out .=     '<h4 style="margin-bottom: 5px;">Inbjudna gäster</h4>';
                foreach ( $invited_guests as $guest ) {
                    $guest_company = get_post_meta($guest->ID, 'guest_company', true);
                    $guest_author_name = get_the_author_meta( 'display_name', $guest->post_author );
                    $out .= '<div class="invited-guest">' . esc_html($guest_author_name) . '&nbsp; - &nbsp;' . esc_html( $guest->post_content ) . '&nbsp; - &nbsp;' . esc_html( $guest_company ) . '</div>';
                }
            }           

            if ( ! empty( $membership_guests ) ) {
                $out .= '<div class="invited-guests">';
                $out .=     '<h4 style="margin-bottom: 5px;">Gäster som sökt medlemskap</h4>';
                foreach ( $membership_guests as $guest ) {                
                    $guest_company = get_post_meta($guest->ID, 'guest_company', true);
                    $guest_author_name = get_the_author_meta( 'display_name', $guest->post_author );
                    $out .= '<div class="invited-guest">' . esc_html($guest_author_name) . '&nbsp; - &nbsp;' . esc_html( $guest->post_content ) . '&nbsp; - &nbsp;' . esc_html( $guest_company ) . '</div>';
                }
            }
      
            $out .= '</div>'; // invited-guests
        }

        if ( empty( $invited_guests ) ) {
            $out .= '<div class="invited-guests">
                        <h4 class="no-invited-guests">Inga inbjudna gäster hittades.</h4>
                     </div>';
        }

        if ( empty( $membership_guests ) ) { 
            $out .= '<div class="invited-guests">    
                        <h4 class="no-invited-guests">Inga gäster som sökt medlemskap hittades.</h4>
                     </div>';
        }
       
        $event->header_row = 'Möteslista för ' . $event->post_title . ' den ' . date_i18n( 'j F Y', strtotime( $event->event_date ) );

        $out .= CreateExcelFile($invited_guests, $membership_guests, $event, $event_status);


        $out .= '</div>'; // applications-list

        return $out;
    }
    add_shortcode( 'applications', 'shortcode_applications' );


    function fenix_applications_excel_option_key( $event_ID, $event_status ) {
        return 'fenix_applications_excel_' . intval( $event_ID ) . '_' . sanitize_key( $event_status );
    }

    function fenix_applications_to_excel_row( $values ) {
        $row = array();
        $idx = 0;
        foreach ( $values as $value ) {
            $safe = htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
            if ( $idx === 0 ) {
                $row[] = '<style height="18">' . $safe;
            } else {
                $row[] = $safe;
            }
            $idx++;
        }
        if ( empty( $row ) ) {
            $row[] = '<style height="18">';
        }
        return $row;
    }

    function fenix_applications_build_members_rows( $event, $event_status ) {
        $members = get_users( array( 'fields' => 'all' ) );
        $rows = array();

        if ( ! empty( $members ) && $event_status === 'foranmalan' ) {
            $registrations = get_post_meta( $event->ID, 'event_registrations', true );
            if ( ! is_array( $registrations ) ) {
                $registrations = array();
            }

            foreach ( $members as $member ) {
                if ( ! in_array( intval( $member->ID ), array_map( 'intval', $registrations ), true ) ) {
                    continue;
                }

                $rows[] = array(
                    'display_name'     => $member->display_name,
                    'company'          => get_user_meta( $member->ID, 'company', true ),
                    'isGoodAt'         => get_user_meta( $member->ID, 'isGoodAt', true ),
                    'specific_search'  => get_user_meta( $member->ID, 'specific_search', true ),
                    'event_status'     => 'foranmalan',
                    'replacer_name'    => '',
                    'absence_reason'   => '',
                );
            }
        }

        if ( ! empty( $members ) && $event_status === 'avanmalan' ) {
            $cancellations = get_post_meta( $event->ID, 'event_cancellations', true );
            if ( ! is_array( $cancellations ) ) {
                $cancellations = array();
            }

            foreach ( $members as $member ) {
                $is_cancelled = in_array( intval( $member->ID ), array_map( 'intval', $cancellations ), true );
                $replacer_name = '';
                $absence_reason = '';

                if ( $is_cancelled ) {
                    $replacer = get_posts( array(
                        'post_type'      => 'replacer',
                        'post_status'    => 'publish',
                        'author'         => intval( $member->ID ),
                        'post_parent'    => intval( $event->ID ),
                        'posts_per_page' => 1,
                    ) );

                    if ( ! empty( $replacer ) ) {
                        $replacer_name = $replacer[0]->post_content;
                    }

                    $absence = get_posts( array(
                        'post_type'      => 'absence_reason',
                        'post_status'    => 'publish',
                        'author'         => intval( $member->ID ),
                        'post_parent'    => intval( $event->ID ),
                        'posts_per_page' => 1,
                    ) );

                    if ( ! empty( $absence ) ) {
                        $absence_reason = $absence[0]->post_title;
                    }
                }

                $rows[] = array(
                    'display_name'     => $member->display_name,
                    'company'          => get_user_meta( $member->ID, 'company', true ),
                    'isGoodAt'         => get_user_meta( $member->ID, 'isGoodAt', true ),
                    'specific_search'  => get_user_meta( $member->ID, 'specific_search', true ),
                    'event_status'     => $is_cancelled ? 'avanmalan' : '',
                    'replacer_name'    => $replacer_name,
                    'absence_reason'   => $absence_reason,
                );
            }
        }

        return $rows;
    }

    function fenix_applications_prepare_guest_rows( $guests ) {
        $rows = array();
        if ( empty( $guests ) || ! is_array( $guests ) ) {
            return $rows;
        }

        foreach ( $guests as $guest ) {
            $author = get_user_by( 'id', $guest->post_author );
            $rows[] = array(
                'author_name'       => $author ? $author->display_name : '',
                'guest_name'        => $guest->post_content,
                'company'           => get_post_meta( $guest->ID, 'guest_company', true ),
                'expertise'         => get_post_meta( $guest->ID, 'guest_expertise', true ),
                'email'             => $guest->post_title,
                'specific_search'   => get_post_meta( $guest->ID, 'guest_specific_search', true ),
                'is_membership'     => get_post_meta( $guest->ID, 'is_membership_guest', true ),
            );
        }

        return $rows;
    }

    function fenix_applications_apply_header_placeholders( $sheet, $event, $event_status ) {
        $event_date = get_post_meta( $event->ID , 'event_date', true );
        $event_location = get_post_meta( $event->ID, 'event_location', true );
        $status_label = ( $event_status === 'avanmalan' ) ? 'Avanmälningar' : 'Föranmälningar';

        $replacements = array(
            '{{EVENT_HEADER_ROW}}' => (string)  $event->header_row,
            '{{EVENT_LOCATION}}' => (string) $event_location,
            '{{EVENT_STATUS}}'   => $status_label,
            '{{GENERATED_AT}}'   => (string) date_i18n( 'Y-m-d H:i' ),
        );

        // Ensure first row header is always populated even if placeholder is missing in template.
        if ( ! empty( $event->header_row ) ) {
            $sheet->setCellValue( 'A1', (string) $event->header_row );
        }

        $max_row = $sheet->getHighestRow();
        $max_col_idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestColumn() );

        for ( $r = 1; $r <= $max_row; $r++ ) {
            for ( $c = 1; $c <= $max_col_idx; $c++ ) {
                $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $c ) . $r;
                $value = $sheet->getCell( $ref )->getValue();
                if ( ! is_string( $value ) || $value === '' ) {
                    continue;
                }
                $new_value = str_replace( array_keys( $replacements ), array_values( $replacements ), $value );
                if ( $new_value !== $value ) {
                    $sheet->setCellValue( $ref, $new_value );
                }
            }
        }
    }

    function fenix_applications_generate_excel( $event, $event_status, $members, $membership_guests, $invited_guest_rows, &$error_message ) {
        $error_message = '';

        $autoload_file = ABSPATH . 'vendor/autoload.php';
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) && file_exists( $autoload_file ) ) {
            require_once $autoload_file;
        }

        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            $error_message = 'Kunde inte skapa Excel-filen. PhpSpreadsheet saknas.';
            return false;
        }

        $template_path = get_stylesheet_directory() . '/assets/templates/meetinglist-template.xlsx';
        if ( ! file_exists( $template_path ) ) {
            $error_message = 'Kunde inte hitta mallfilen.';
            return false;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $template_path );
            $sheet = $spreadsheet->getSheetByName( 'Möteslista' );
            if ( ! $sheet ) {
                $sheet = $spreadsheet->getSheet( 0 );
            }

            fenix_applications_apply_header_placeholders( $sheet, $event, $event_status );

            $highest_row = $sheet->getHighestRow();
            $highest_col_idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestColumn() );

            $find_row = function( $needle, $column = 1, $contains = false ) use ( $sheet, $highest_row ) {
                $needle_norm = mb_strtolower( trim( (string) $needle ) );
                for ( $r = 1; $r <= $highest_row; $r++ ) {
                    $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $column ) . $r;
                    $val_norm = mb_strtolower( trim( (string) $sheet->getCell( $ref )->getValue() ) );
                    if ( $contains ) {
                        if ( $needle_norm !== '' && strpos( $val_norm, $needle_norm ) !== false ) {
                            return $r;
                        }
                    } else {
                        if ( $val_norm === $needle_norm ) {
                            return $r;
                        }
                    }
                }
                return 0;
            };

            $membership_title_row = $find_row( 'gäster som ansökt om medlemskap:', 1, false );
            $invited_title_row = $find_row( 'gäster:', 1, false );
            $after_invited_anchor_row = $find_row( 'Vill du bli medlem', 1, true );
            $hardcoded_block_start_row = $find_row( 'natverketfenix.se', 1, true );

            // Members table data starts at fixed row 6.
            $members_data_start_row = 6;
            // Membership and invited tables are anchored by section titles.
            $membership_data_start_row = $membership_title_row ? ( $membership_title_row + 2 ) : 0;
            $invited_data_start_row = $invited_title_row ? ( $invited_title_row + 2 ) : 0;

            if ( ! $membership_title_row || ! $invited_title_row || ! $membership_data_start_row || ! $invited_data_start_row ) {
                $error_message = 'Kunde inte hitta alla tabellsektioner i mallfilen. Kontrollera rubrikerna i tabben Möteslista.';
                return false;
            }

            $write_table_rows = function( $data_start_row, $next_anchor_row, $column_map, $rows, $empty_rows_before_next_anchor = 0, $merge_columns = array() ) use ( $sheet ) {
                $last_data_col = 0;
                foreach ( $column_map as $col_idx ) {
                    if ( $col_idx > $last_data_col ) {
                        $last_data_col = $col_idx;
                    }
                }

                $end_row = max( $data_start_row, intval( $next_anchor_row ) - 1 - intval( $empty_rows_before_next_anchor ) );
                $available_rows = $end_row - $data_start_row + 1;
                $needed_rows = max( 1, count( $rows ) );

                if ( $needed_rows > $available_rows ) {
                    $insert_count = $needed_rows - $available_rows;
                    $sheet->insertNewRowBefore( intval( $next_anchor_row ), $insert_count );
                    $end_row += $insert_count;
                }

                for ( $r = $data_start_row; $r <= $end_row; $r++ ) {
                    for ( $c = 1; $c <= $last_data_col; $c++ ) {
                        $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $c ) . $r;
                        $sheet->setCellValue( $ref, null );
                    }
                }

                $border_style = array(
                    'borders' => array(
                        'allBorders' => array(
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => array( 'argb' => 'FF000000' ),
                        ),
                    ),
                );

                $row_idx = 0;
                foreach ( $rows as $row_values ) {
                    $excel_row = $data_start_row + $row_idx;
                    foreach ( $column_map as $key => $col_idx ) {
                        $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx ) . $excel_row;
                        $sheet->setCellValue( $ref, isset( $row_values[ $key ] ) ? (string) $row_values[ $key ] : '' );
                        $sheet->getStyle( $ref )->applyFromArray( $border_style );
                    }

                    // Merge requested columns for this row (e.g. C:D and G:H in members table)
                    foreach ( $merge_columns as $pair ) {
                        if ( ! is_array( $pair ) || count( $pair ) !== 2 ) {
                            continue;
                        }
                        $start_col = intval( $pair[0] );
                        $end_col = intval( $pair[1] );
                        if ( $start_col <= 0 || $end_col <= $start_col ) {
                            continue;
                        }
                        $start_ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $start_col ) . $excel_row;
                        $end_ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $end_col ) . $excel_row;
                        $range = $start_ref . ':' . $end_ref;
                        foreach ( $sheet->getMergeCells() as $merged_range ) {
                            if ( strpos( $merged_range, $start_ref . ':' ) === 0 ) {
                                $sheet->unmergeCells( $merged_range );
                            }
                        }
                        $sheet->mergeCells( $range );
                        $sheet->getStyle( $range )->applyFromArray( $border_style );
                    }

                    $sheet->getRowDimension( $excel_row )->setRowHeight( 18);
                    $row_idx++;
                }
            };

            // Keep two empty rows between hardcoded block and table 2 section title
            if ( $membership_title_row > 0 ) {
                $sheet->insertNewRowBefore( $membership_title_row, 2 );
                $membership_title_row += 2;
                if ( $invited_title_row > 0 ) {
                    $invited_title_row += 2;
                }
                if ( $after_invited_anchor_row > 0 ) {
                    $after_invited_anchor_row += 2;
                }

                $membership_data_start_row = $membership_title_row + 2;
                $invited_data_start_row = $invited_title_row ? ( $invited_title_row + 2 ) : 0;
            }

            // Keep exactly 3 empty rows between table 2 and table 3 (before table 3 title section).
            if ( $invited_title_row > 0 ) {
                $sheet->insertNewRowBefore( $invited_title_row, 2 );
                $invited_title_row += 2;
                if ( $after_invited_anchor_row > 0 ) {
                    $after_invited_anchor_row += 2;
                }
                $invited_data_start_row = $invited_title_row + 2;
            }

            // Keep exactly 3 empty rows after table 3 and before hardcoded rows.
            if ( $after_invited_anchor_row > 0 ) {
                $sheet->insertNewRowBefore( $after_invited_anchor_row, 2 );
                $after_invited_anchor_row += 2;
            }

            // Write from bottom to top so row insertions don't break earlier anchors
            $invited_next_anchor_row = $after_invited_anchor_row ? $after_invited_anchor_row : ( $sheet->getHighestRow() + 1 );
            $empty_rows_after_table3 = 3;
            // Anchor padding tuned so visible blank rows between table 3 and hardcoded block are exactly 3.
            $anchor_padding_after_table3 = $empty_rows_after_table3 + 2;
            $write_table_rows(
                $invited_data_start_row,
                $invited_next_anchor_row,
                array(
                    'guest_name'  => 1,
                    'company'     => 2,
                    'expertise'   => 4,
                    'email'       => 7,
                    'author_name' => 8,
                ),
                $invited_guest_rows,
                0,
                array(
                    array( 2, 3 ),
                    array( 4, 6 ),
                )
            );

            // Ensure deterministic border cleanup after table 3 (Expertområde D:F):
            // keep bottom border on last data row, clear all borders in the 3 blank rows below.
            $table3_rows_written = max( 1, count( $invited_guest_rows ) );
            $table3_last_data_row = $invited_data_start_row + $table3_rows_written - 1;
            $gap_start_row = $table3_last_data_row + 1;
            $gap_end_row = $table3_last_data_row + $empty_rows_after_table3;

            $sheet->getStyle( 'D' . $table3_last_data_row . ':F' . $table3_last_data_row )->applyFromArray(
                array(
                    'borders' => array(
                        'bottom' => array(
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => array( 'argb' => 'FF000000' ),
                        ),
                    ),
                )
            );

            for ( $r = $gap_start_row; $r <= $gap_end_row; $r++ ) {
                $range = 'D' . $r . ':F' . $r;
                $sheet->getStyle( $range )->applyFromArray(
                    array(
                        'borders' => array(
                            'allBorders' => array(
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                            ),
                        ),
                    )
                );
            }

            $write_table_rows(
                $membership_data_start_row,
                $invited_title_row,
                array(
                    'guest_name'       => 1,
                    'company'          => 2,
                    'expertise'        => 4,
                    'email'            => 7,
                    'specific_search'  => 8,
                ),
                $membership_guests,
                3, // Keep 3-row spacing between table 2 and table 3.
                array(
                    array( 2, 3 ),
                    array( 4, 6 ),
                )
            );

            $write_table_rows(
                $members_data_start_row,
                $hardcoded_block_start_row ? $hardcoded_block_start_row : $membership_title_row,
                array(
                    'display_name'    => 1,
                    'company'         => 2,
                    'isGoodAt'        => 3,
                    'absence_reason'  => 5,
                    'replacer_name'   => 6,
                    'specific_search' => 7,
                ),
                $members,
                2,
                array(
                    array( 3, 4 ),
                    array( 7, 8 ),
                )
            );

            foreach ( $spreadsheet->getWorksheetIterator() as $work_sheet ) {
                $work_sheet->getDefaultRowDimension()->setRowHeight( 18 );
                $max_r = $work_sheet->getHighestRow();
                for ( $r = 1; $r <= $max_r; $r++ ) {
                    $work_sheet->getRowDimension( $r )->setRowHeight( 18 );
                }
            }

            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'applications-files';
            if ( ! file_exists( $target_dir ) ) {
                wp_mkdir_p( $target_dir );
            }

            $filename = 'moteslista-fenix-event-' . intval( $event->ID ) . '-' . sanitize_key( $event_status ) . '.xlsx';
            $target_path = trailingslashit( $target_dir ) . $filename;

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
            $writer->save( $target_path );

            return array(
                'path'    => $target_path,
                'url'     => trailingslashit( $upload_dir['baseurl'] ) . 'applications-files/' . $filename,
                'source'  => 'generated',
                'updated' => time(),
            );
        } catch ( Exception $e ) {
            $error_message = 'Kunde inte skapa Excel-filen: ' . $e->getMessage();
            return false;
        }
    }

    function CreateExcelFile( $invited_guests, $membership_guests, $event, $event_status ) {
        $out = '';
        $messages = array( 'errors' => array(), 'success' => array(), 'warnings' => array() );

        $option_key = fenix_applications_excel_option_key( $event->ID, $event_status );
        $excel_state = get_option( $option_key, array() );
        $show_regenerate_confirm = false;

        $members = fenix_applications_build_members_rows( $event, $event_status );
        $membership_rows = fenix_applications_prepare_guest_rows( $membership_guests );
        $invited_guest_rows = fenix_applications_prepare_guest_rows( $invited_guests );

        // Generate system file
        if ( isset( $_POST['applications_generate_excel_submit'] ) ) {
            if ( ! isset( $_POST['applications_excel_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['applications_excel_nonce'] ), 'applications_excel_action' ) ) {
                $messages['errors'][] = 'Ogiltig formulärförfrågan.';
            } else {
                $confirm_override = isset( $_POST['applications_confirm_regenerate'] ) ? intval( wp_unslash( $_POST['applications_confirm_regenerate'] ) ) : 0;

                if ( ! empty( $excel_state ) && isset( $excel_state['source'] ) && $excel_state['source'] === 'uploaded' && $confirm_override !== 1 ) {
                    $messages['warnings'][] = 'Filen har manuella ändringar från en uppladdad version. Om du skapar en ny mötesfil nu, förloras dessa ändringar.';
                    $show_regenerate_confirm = true;
                } else {
                    $error_message = '';
                    $generated_state = fenix_applications_generate_excel( $event, $event_status, $members, $membership_rows, $invited_guest_rows, $error_message );
                    if ( $generated_state === false ) {
                        $messages['errors'][] = $error_message;
                    } else {
                        update_option( $option_key, $generated_state, false );
                        $excel_state = $generated_state;
                        $messages['success'][] = 'Mötesfilen har skapats.';
                    }
                }
            }
        }

        // Upload/replace manually edited file
        if ( isset( $_POST['applications_upload_excel_submit'] ) ) {
            if ( ! isset( $_POST['applications_excel_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['applications_excel_nonce'] ), 'applications_excel_action' ) ) {
                $messages['errors'][] = 'Ogiltig formulärförfrågan.';
            } elseif ( empty( $_FILES['applications_excel_file']['name'] ) ) {
                $messages['errors'][] = 'Välj en Excel-fil att ladda upp.';
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';

                $overrides = array(
                    'test_form' => false,
                    'mimes'     => array(
                        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ),
                );

                $uploaded = wp_handle_upload( $_FILES['applications_excel_file'], $overrides );

                if ( isset( $uploaded['error'] ) ) {
                    $messages['errors'][] = 'Uppladdning misslyckades: ' . $uploaded['error'];
                } else {
                    $new_state = array(
                        'path'    => $uploaded['file'],
                        'url'     => $uploaded['url'],
                        'source'  => 'uploaded',
                        'updated' => time(),
                    );
                    update_option( $option_key, $new_state, false );
                    $excel_state = $new_state;
                    $messages['success'][] = 'Excel-filen har laddats upp och ersatt den tidigare filen.';
                }
            }
        }

        if ( ! empty( $messages['errors'] ) ) {
            $out .= '<div class="applications-errors">';
            foreach ( $messages['errors'] as $msg ) {
                $out .= '<div class="applications-error">' . esc_html( $msg ) . '</div>';
            }
            $out .= '</div>';
        }
        if ( ! empty( $messages['warnings'] ) ) {
            $out .= '<div class="applications-warnings">';
            foreach ( $messages['warnings'] as $msg ) {
                $out .= '<div class="applications-warning">' . esc_html( $msg ) . '</div>';
            }
            $out .= '</div>';
        }
        if ( ! empty( $messages['success'] ) ) {
            $out .= '<div style="margin-top: 30px;">';
            foreach ( $messages['success'] as $msg ) {
                $out .= '<div class="applications-success">' . esc_html( $msg ) . '</div>';
            }
            $out .= '</div>';
        }

        $out .= '<form method="post" class="applications-excel-form">';
        $out .= wp_nonce_field( 'applications_excel_action', 'applications_excel_nonce', true, false );
        $out .= '<input type="hidden" name="event_id" value="' . esc_attr( $event->ID ) . '" />';
        $out .= '<input type="hidden" name="event_status" value="' . esc_attr( $event_status ) . '" />';

        $out .= '<p>';
        $out .= '<button type="submit" name="applications_generate_excel_submit" class="submit-button" data-tooltip="Skapa en mötesfil i excel format för detta eventet">Skapa mötesfil</button>';
        if ( $show_regenerate_confirm ) {
            $out .= ' <button type="submit" name="applications_generate_excel_submit" value="1" class="submit-button" data-tooltip="Skapa ny fil och skriv över manuella ändringar">Skapa ändå (manuella ändringar förloras)</button>';
            $out .= '<input type="hidden" name="applications_confirm_regenerate" value="1" />';
        }
        $out .= '</p>';
        $out .= '</form>';

        $out .= '<form method="post" enctype="multipart/form-data" class="applications-excel-upload-form">';
        $out .= wp_nonce_field( 'applications_excel_action', 'applications_excel_nonce', true, false );
        $out .= '<input type="hidden" name="event_id" value="' . esc_attr( $event->ID ) . '" />';
        $out .= '<input type="hidden" name="event_status" value="' . esc_attr( $event_status ) . '" />';
       

        if ( ! empty( $excel_state['path'] ) && file_exists( $excel_state['path'] ) ) {
            $out .= '<p style="margin-top: 30px;">
                        <label for="applications_excel_file" style="margin-bottom: -5px;">Ladda upp redigerad mötesfil:</label>
                        <input type="file" id="applications_excel_file" name="applications_excel_file" accept=".xlsx" /> 
                        <button type="submit" name="applications_upload_excel_submit" class="submit-button">Ladda upp fil</button></p>';
            $out .= '</form>';

            $download_url = add_query_arg(
                array(
                    'action'       => 'fenix_download_applications_excel',
                    'event_id'     => intval( $event->ID ),
                    'event_status' => sanitize_key( $event_status ),
                    '_wpnonce'     => wp_create_nonce( 'fenix_download_excel_' . intval( $event->ID ) . '_' . sanitize_key( $event_status ) ),
                ),
                admin_url( 'admin-post.php' )
            );

            $out .= '<p class="applications-download-link">
                        <a href="' . esc_url( $download_url ) . '">Ladda ner aktuell mötesfil</a>
                     </p>';
        }
        else {
            $out .= '</form>';
        }
        

        return $out;
    }

    function fenix_download_applications_excel_handler() {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Du måste vara inloggad för att ladda ner filen.' );
        }

        $event_id = isset( $_GET['event_id'] ) ? intval( wp_unslash( $_GET['event_id'] ) ) : 0;
        $event_status = isset( $_GET['event_status'] ) ? sanitize_key( wp_unslash( $_GET['event_status'] ) ) : '';
        $nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';

        if ( $event_id <= 0 || ( $event_status !== 'foranmalan' && $event_status !== 'avanmalan' ) ) {
            wp_die( 'Ogiltig nedladdningsförfrågan.' );
        }

        if ( ! wp_verify_nonce( $nonce, 'fenix_download_excel_' . $event_id . '_' . $event_status ) ) {
            wp_die( 'Ogiltig formulärförfrågan.' );
        }

        $option_key = fenix_applications_excel_option_key( $event_id, $event_status );
        $excel_state = get_option( $option_key, array() );

        if ( empty( $excel_state['path'] ) || ! file_exists( $excel_state['path'] ) ) {
            wp_die( 'Filen kunde inte hittas.' );
        }

        $file_path = $excel_state['path'];
        $download_name = basename( $file_path );

        if ( ob_get_length() ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
        header( 'Content-Length: ' . filesize( $file_path ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $file_path );

        exit;
    }
    add_action( 'admin_post_fenix_download_applications_excel', 'fenix_download_applications_excel_handler' );
