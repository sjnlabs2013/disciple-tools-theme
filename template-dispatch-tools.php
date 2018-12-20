<?php
/*
Template Name: Dispatch Tools
*/
if ( ! current_user_can( 'manage_dt' ) ) {
    wp_safe_redirect( '/settings' );
}

$diagnosis_results = Disciple_Tools_Dispatch_Tools::get_diagnosis_results();
function dt_status_icons() { ?>
    <img class="verified" style="display:inline-block" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/verified.svg' )?>" />
    <img class="invalid" style="display:inline-block" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' )?>" />
    <?php
}
get_header();
?>

<div id="content">
    <div id="inner-content">
        <div class="bordered-box">
            <h3>Undefined Sources</h3>
            <h5>Sources set on contacts that are not defined
                <span id="undefined_sources"><?php dt_status_icons()?></span>
            </h5>
            <?php if ( $diagnosis_results["undefined_sources"]["has_problem"] ): ?>
                <a href="<?php echo esc_html( get_site_url() ) ?>/wp-admin/admin.php?page=dt_options&tab=custom-lists">See existing source list</a>
                <ul>
                <?php foreach ( $diagnosis_results["undefined_sources"]["sources"] as $source_key => $number ): ?>
                    <li>
                        <?php echo esc_html( $source_key ) ?> (<?php echo esc_html( $number ) ?> contacts)
                        <br><a>Create as new source</a>
                        <br><a>Assign all to an existing source</a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>


            <h5>Contacts without a source set (<?php echo esc_html( $diagnosis_results["contacts_without_sources"]["total"] > 0 ? $diagnosis_results["contacts_without_sources"]["total"] : '' ) ?> contacts)
                <span id="contacts_without_sources"><?php dt_status_icons()?></span>
            </h5>

            <?php if ( $diagnosis_results["contacts_without_sources"]["has_problem"] ) :
                foreach ( $diagnosis_results["contacts_without_sources"]["contacts"] as $contact ): ?>
                    <a href="<?php echo esc_html( get_site_url() ) ?>/contacts/<?php echo esc_html( $contact["ID"] )?>"><?php echo esc_html( $contact["ID"] )?></a>
                <?php endforeach; ?>
            <?php endif; ?>

            <br>
            <br>
            <br>
            <h3>Users</h3>
            <h5>Users who need to accept contacts and have not in a long time
                <span id="users_accept"><?php dt_status_icons()?></span>
            </h5>

        </div>
    </div>
</div>

<?php get_footer(); ?>
