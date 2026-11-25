<?php

function shortcode_all_members( $atts )
{
    $args = array('orderby' => 'registered', 'order'   => 'DESC', 'fields'  => 'all', ); 

    $q = new WP_User_Query( $args ); 
    $users = $q->get_results(); 


    $deactivated_users = array();
    
    foreach ( $users as $user ) {   
        $user_id = is_object($user) ? intval($user->ID) : intval($user);
        
        if ( (string) get_user_meta( $user_id, 'deactivated', true ) === '1' ) {
            $deactivated_users[] = $user;
        } 
    }
    
    $atts = shortcode_atts( array( 'per_page' => 20 ), $atts, 'all_members' );
    $default_per_page = max( 1, intval( $atts['per_page'] ) );
    $query_per_page = isset( $_GET['members_per_page'] ) ? intval( wp_unslash( $_GET['members_per_page'] ) ) : 0;
    $per_page = $query_per_page > 0 ? min( 100, $query_per_page ) : $default_per_page;
    $members_page = isset( $_GET['members_page'] ) ? intval( wp_unslash( $_GET['members_page'] ) ) : 0;
    $paged = max( 1, $members_page, get_query_var( 'paged' ), get_query_var( 'page' ) ); 
    $offset = ( $paged - 1 ) * $per_page; 
    $args = array( 'number'  => $per_page, 'offset'  => $offset, 'orderby' => 'registered', 'order'   => 'DESC', 'fields'  => 'all', ); 
    
    $q = new WP_User_Query( $args ); 
    $users = $q->get_results(); 

    $active_users = array();
    
    foreach ( $users as $user ) {   
        if ( (string) get_user_meta( $user->id, 'deactivated', true ) !== '1' ) {
            $active_users[] = $user;
        }
    }

    if ( empty( $users ) ) 
    { 
        error_log( 'all_members shortcode: no members. Query vars: ' . print_r( $q->query_vars, true ) . ' total:' . $q->get_total() ); 
        
        return '<p>No members found in db.</p>'; 
    }
    

    $out = '<div class="all-members-wrap">
                <div class="member-search-wrap">
                    <input type="text" id="memberSearch" placeholder="🔍 Sök medlem">
                </div>

                <div class="all-members-inner-wrap">
                     <h3 style="margin-bottom: 30px;"><strong>Aktiva medlemmar</strong></h3>';
    
    foreach ( $active_users as $u ) {                     
        $profilePhoto = '';
        $att_id = get_user_meta($u->ID, 'profile_picture', true );

        if ( $att_id ) {
            $profilePhoto = wp_get_attachment_image( $att_id, array( 64, 64 ), false, array( 'class' => 'avatar' ) );
        } 
        else {
            $profilePhoto = get_avatar($u->ID, 64 );
        }

        // Resolve cover image URL (if present) and build inline style only when available
        $cover_url = '';
        if ( function_exists( 'ollie_get_user_cover_url' ) ) {
            $cover_url = ollie_get_user_cover_url( $u->ID, 'full' );
        } else {
            $cover_att = get_user_meta( $u->ID, 'profile_cover', true );
            if ( $cover_att ) {
                $cover_url = wp_get_attachment_image_url( $cover_att, 'full' );
            }
        }

        $cover_style = 'background-image:url(' . esc_url( $cover_url ) . ')';
        
        $email = isset( $u->user_email ) ? $u->user_email : '';
        $website = isset( $u->user_url ) ? $u->user_url : get_user_meta( $u->ID, 'user_url', true );
        $company =  get_user_meta( $u->ID, 'company', true );
        $linkedin = isset( $u->linkedin ) ? $u->linkedin : get_user_meta( $u->ID, 'linkedin', true );
        $biography= isset( $u->description ) ? $u->description : get_user_meta( $u->ID, 'description', true );
        
        $out .= '<div class="member-wrap"
                    data-name="' . esc_attr( $u->display_name ) . '"
                    data-company="' . esc_attr( $company ) . '"
                    data-email="' . esc_attr( $email ) . '"
                    data-linkedin="' . esc_attr( $linkedin ) . '"
                    data-website="' . esc_attr( $website ) . '"                    
                    data-description="' . esc_attr( $biography ) . '">';

        $out .= '<div class="member-cover"' . ( $cover_style ? ' style="' . $cover_style . '"' : '' ) . '></div>';
        // Resolve the edit-member page permalink by path if it exists, fallback to the slug URL.
        $edit_page = get_page_by_path( 'andra-medlem' );
        $base = $edit_page ? get_permalink( $edit_page->ID ) : site_url( '/andra-medlem/' );
        $edit_link = esc_url( add_query_arg( array( 'user_id' => $u->ID ), $base ) );
        

        $out .= '<div class="member-card">';
    
        if ( current_user_can( 'manage_options' ) ) {
            $out .= '<div class="member-photo"><a href="' . $edit_link . '">' . $profilePhoto . '</a></div>';
            $out .= '<div class="member profile-display-name"><a href="' . $edit_link . '">' . esc_html( $u->display_name ) . '</a></div>';
        } else {
            $out .= '<div class="member-photo">' . $profilePhoto . '</div>';
            $out .= '<div class="member profile-display-name">' . esc_html( $u->display_name ) . '</div>';
        }

        $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Epost: </span>' . esc_html( $email ) . '</div>';
        $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Företag: </span>' . esc_html( $company ) . '</div>';
        if ( $linkedin != '' ) {
            $out .= '<div class="member-item-wrap">
                        <span class="member-profile-item-title">
                            <a href="' . esc_url( $linkedin ) . '" target="_blank" rel="noopener">LinkedIn:</a>
                        </span>
                     </div>';
        }
        else {
            $out .= '<div class="member-item-wrap">
                        <span class="member-profile-item-title">LinkedIn:</span>
                     </div>';
        }
        if ( $website != '' ) {
            $out .= '<div class="member-item-wrap">
                        <span class="member-profile-item-title">
                            <a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">Webbplats:</a>
                        </span>
                     </div>';
        }
        else {
            $out .= '<div class="member-item-wrap">
                        <span class="member-profile-item-title">Webbplats:</span>
                     </div>';
        }
        
        $out .= '<div class="member-item-wrap profile-bio">' . wp_kses_post( wpautop( $biography ) ) . '</div>';
        $out .= '</div>'; // member-card
        $out .= '</div>'; // member-wrap
        
    } 
   
    $out .=    '</div>'; // Stänger all-members-inner-wrap
   
    $out .= '</div>'; // Stänger all-members-wrap

    $total_users = intval( $q->get_total() );
    $total_pages = $per_page > 0 ? (int) ceil( $total_users / $per_page ) : 1;

    if ( $total_pages > 1 ) {
        $out .= '<div class="all-members-pagination">';

        if ( $paged > 1 ) {
            $prev_url = esc_url( add_query_arg( 'members_page', $paged - 1, remove_query_arg( 'members_page' ) ) );
            $out .= '<a class="members-page-link members-page-prev" href="' . $prev_url . '">« Föregående</a> ';
        }

        if ( $paged < $total_pages ) {
            $next_url = esc_url( add_query_arg( 'members_page', $paged + 1, remove_query_arg( 'members_page' ) ) );
            $out .= '<a class="members-page-link members-page-next" href="' . $next_url . '">Nästa»</a> ';
        }

        $out .= ' &nbsp; ' . $total_pages . ' sidor
                 </div>';
    }
  $out .= '<hr class="separator" />';
        
    $members_page = isset($_GET['members_page']) ? intval(wp_unslash($_GET['members_page'])) : 1;

    if ($members_page < 1) {
        $members_page = 1;
    }

    // Only show on first pagination page
    if ($members_page === 1) {
        $out .= '<div class="all-members-wrap"  style="margin-top: 50px;">                   
                    <div class="all-members-inner-wrap" style="margin-top: 30px;">                        
                        <h3 style="margin-bottom: 10px;"><strong>Avaktiverade medlemmar</strong></h3>
                        <div style="margin-bottom: 30px;">Medlemmar som gått ur nätverket Fenix visas också här.</div>';
        if ( empty( $deactivated_users ) ) {
            $out .= '<div>Finns inga avaktiverade medlemmar.</div>';
        } 
        else {
            foreach ( $deactivated_users as $user ) {                     
                $profilePhoto = '';
                $att_id = get_user_meta($user->ID, 'profile_picture', true );

                if ( $att_id ) {
                    $profilePhoto = wp_get_attachment_image( $att_id, array( 64, 64 ), false, array( 'class' => 'avatar' ) );
                } 
                else {
                    $profilePhoto = get_avatar($user->ID, 64 );
                }

                // Resolve cover image URL (if present) and build inline style only when available
                $cover_url = '';
                if ( function_exists( 'ollie_get_user_cover_url' ) ) {
                    $cover_url = ollie_get_user_cover_url( $user->ID, 'full' );
                } else {
                    $cover_att = get_user_meta( $user->ID, 'profile_cover', true );
                    if ( $cover_att ) {
                        $cover_url = wp_get_attachment_image_url( $cover_att, 'full' );
                    }
                }

                $cover_style = 'background-image:url(' . esc_url( $cover_url ) . ')';
                
                $email = isset( $user->user_email ) ? $user->user_email : '';
                $website = isset( $user->user_url ) ? $user->user_url : get_user_meta( $user->ID, 'user_url', true );
                $company =  get_user_meta( $user->ID, 'company', true );
                $linkedin = isset( $user->linkedin ) ? $user->linkedin : get_user_meta( $user->ID, 'linkedin', true );
                
                $out .= '<div class="member-wrap deactivated-member-wrap"
                            data-name="' . esc_attr( $user->display_name ) . '"
                            data-company="' . esc_attr( $company ) . '"
                            data-email="' . esc_attr( $email ) . '"
                            data-linkedin="' . esc_attr( $linkedin ) . '"
                            data-website="' . esc_attr( $website ) . '">';

                $out .=    '<div class="member-cover"' . ( $cover_style ? ' style="' . $cover_style . '"' : '' ) . '></div>';
                
                // Resolve the edit-member page permalink by path if it exists, fallback to the slug URL.
                $edit_page = get_page_by_path( 'andra-medlem' );
                $base = $edit_page ? get_permalink( $edit_page->ID ) : site_url( '/andra-medlem/' );
                $edit_link = esc_url( add_query_arg( array( 'user_id' => $user->ID ), $base ) );
                

                $out .= '<div class="member-card">';
            
                if ( current_user_can( 'manage_options' ) ) {
                    $out .= '<div class="member-photo"><a href="' . $edit_link . '">' . $profilePhoto . '</a></div>';
                    $out .= '<div class="member profile-display-name"><a href="' . $edit_link . '">' . esc_html( $user->display_name ) . '</a></div>';
                } else {
                    $out .= '<div class="member-photo">' . $profilePhoto . '</div>';
                    $out .= '<div class="member profile-display-name">' . esc_html( $user->display_name ) . '</div>';
                }

                $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Epost: </span>' . esc_html( $email ) . '</div>';
                $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Företag: </span>' . esc_html( $company ) . '</div>';
                
                if ( $linkedin != '') {
                    $out .= '<div class="member-item-wrap">
                                <span class="member-profile-item-title">
                                    <a href="' . esc_url( $linkedin ) . '" target="_blank" rel="noopener">LinkedIn Link:</a>
                                </span>
                             </div>';
                }
                else {
                    $out .= '<div class="member-item-wrap">
                                <span class="member-profile-item-title">LinkedIn:</span>
                             </div>';
                }

                if ( $website != '') {
                        $out .= '<div class="member-item-wrap">
                                    <span class="member-profile-item-title">
                                        <a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">Webbplats:</a>
                                    </span>
                                </div>';
                }
                else {
                    $out .= '<div class="member-item-wrap">
                                <span class="member-profile-item-title">Webbplats:</span>
                             </div>';
                }
                
                $out .= '</div>'; // member-card
                $out .= '</div>'; // member-wrap
                
            } 
            
            $out .=     '</div>';  //Stänger all-members-inner-wrap
            $out .= '</div>';  // Stänger all-members-wrap
        }
    }

    // Sökfunktionalitet 
    $out .= '
        <script>
        document.addEventListener("DOMContentLoaded", function() {

            const url = new URL(window.location.href);
            const currentPerPage = parseInt(url.searchParams.get("members_per_page") || "", 10);
            const desiredPerPage = window.innerWidth < 705 ? 10 : 20;

            if (currentPerPage !== desiredPerPage) {
                url.searchParams.set("members_per_page", String(desiredPerPage));
                url.searchParams.delete("members_page");
                window.location.replace(url.toString());
                return;
            }

            const searchInput = document.getElementById("memberSearch");

            if (!searchInput) return;

            searchInput.addEventListener("keyup", function() {

                const searchTerm = this.value.toLowerCase();

                document.querySelectorAll(".member-wrap").forEach(function(member) {

                    const searchText =
                        (
                            member.dataset.name + " " +
                            member.dataset.company + " " +
                            member.dataset.email + " " +
                            member.dataset.website + " " +
                            member.dataset.linkedin + " " +
                            member.dataset.description
                        ).toLowerCase();

                    if (searchText.includes(searchTerm)) {
                        member.style.display = "";
                    } else {
                        member.style.display = "none";
                    }

                });

            });

        });
        </script>';
    
    return $out; 
}

add_shortcode( 'all_members', 'shortcode_all_members' );