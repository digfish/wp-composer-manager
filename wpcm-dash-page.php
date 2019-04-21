<?php
global $composer_launcher;
wp_enqueue_script( 'json2' );
wp_enqueue_script( 'jquery-ui-dialog' );

$wp_scripts = wp_scripts();
wp_enqueue_style( 'plugin_name-admin-ui-css',
	// https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css
	'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css',
	false,
	'0.1',
	false );

?>

<div class="wrap">
    <h1><?= WPCM_PLUGIN_NAME ?> dashboard</h1>
</div>

<h3>Composer initialization</h3>
<?php

if ( composerPharExists() ) {
	echo "<P>The <SPAN style='font-family: monospace; color: green'>composer.phar</SPAN>  is present!</P>";
} else {
	echo "<P>Install composer clicking this <A href='" . ci_url( 'download-composer' ) . ">link</A></P>";
}

if ( homeExists() ) {
	echo "<P>The composer home at <SPAN style='font-family: monospace; color: green'>" . WP_CONTENT_DIR . "/composer-home </SPAN>  exists! Great!</P>";
} else {
	echo "<P>Initialize the composer home dir <A href='" . ci_url( 'init-home' ) . "'>clicking here</A></P>";
}


if ( $composer_launcher ) {
	$executeOutput = $composer_launcher->getExecuteOutput();
	if ( ! empty( $executeOutput ) ) {
		print "<PRE style='color:blue'>$executeOutput</PRE>";
	}
}
if ( ! empty( $output ) ) {
	echo "<P>The last composer call terminated with success. <A href='javascript:dialogComposerOutput()'>Click here to see its output</A></P>";
};

?>
<h3>Wordpress paths with composer-enabled projects</h3>
<?php
$composed_projects = composer_json_check( ABSPATH );
//d($composed_projects);


$table_data = array();
$i          = 1;
foreach ( $composed_projects as $path ) {
	$table_data[] = array(
		'title' => title_from_path( $path ),
		'path'  => $path
	);
}
//d($table_data);
include_once "wpcm-table.php";

echo "<FORM method='POST' action='" . ci_url( 'select-update' ) . "'>";
$table = new WpCmTable();
$table->set_data( $table_data );
$table->prepare_items();
$table->display();
submit_button( 'Composer update the select project', 'primary', '' );
echo "</FORM>";
?>

<h2>Composer global actions</h2>
<?php echo ci_link( ci_url( 'update' ), 'Update' ); ?> |
<?php echo ci_link( ci_url( 'require' ), 'Require' ); ?> |
<?php echo ci_link( ci_url( 'empty-cache' ), 'Empty Composer Cache' ); ?>

<div id="dialog-message" class="dialog" style="" title="">
    <p>
    <p>Composer working<span class="reticences"></span></p>
    <p>please wait<span class="reticences"></span></p>
    <!-- <textarea id="composerOutput" style='font-family: monospace; overflow: scroll ; width: 100%' ></textarea> -->
    </p>
</div>

<div id="search-composer-dialog" class="dialog">
    <h4>Requiring a new package on <span id="project-to-check"></span></h4>
    <p>Input a package to composer search:<input type="text" name="package_to_search">
        <button id='composerButtonSearch'>Search</button>
    </p>
    <div class="above-results"></div>
    <div class="composer-search-results-container"></div>
</div>

<div id="composerjson-view-dialog" class="dialog" style="width: 400px">
    <div class="contents" style="font-family: monospace; font-size: 0.8em; ">
        <PRE class='wrapped'>
    </PRE>
    </div>
</div>

<div id="composer-output-dialog" class="dialog" style='width: 400px'>
	<?php
	if ( ! empty( $output ) ) {
		echo "<PRE style='font-size:0.8em'>$output</PRE>";
	}
	?>
</div>


<script type="text/javascript">

    function submitActionDialog() {
        $('#dialog-message').dialog();
        //$(this).submit();
        setInterval(waitingForComposerDialog, 1000);
        return true;
    }

    function showComposerSearchDialog(anchor) {
        arrayPairs = grabQueryPairsFromAnchor(anchor);
        $('#search-composer-dialog #project-to-check').text(arrayPairs['project_to_check']);
        $('#search-composer-dialog').dialog().data('project_to_check', arrayPairs['project_to_check']);
    }

    $ = jQuery;

    $(function () {
        $('.dialog').hide();

        $('form').submit(function (evt) {
            // $('#dialog-message').dialog();
            // //$(this).submit();
            // setInterval(waitingForComposerDialog,1000);
            // return true;
            submitActionDialog();
        });

        // enter submits the search for composer (key code 13)
        $('[name=package_to_search]').keyup(function (evt) {
            if (evt.which == 13) {
                $('#composerButtonSearch').trigger('click');
            }
        });


        $('.row-actions .update a').click(function (click) {
            submitActionDialog();
        });
        $('.row-actions .require a').click(function (click) {
            showComposerSearchDialog(this);
            return false;
        });
        $('.row-actions .view a').click(function (click) {
            viewComposerJsonOnProject(this);
            return false;
        });

        $('#composerButtonSearch').click(function (click) {
            $.get(ajaxurl + '?action=composer_search',
                {q: $('[name=package_to_search]').val()},
                function (json) {
                    buffer = "";
                    //parsed =	$.parseJSON(json);
                    parsed = json;
                    console.log(parsed);

                    if (parsed.length == 0) {
                        buffer += "<li><h2>No results!</h2></li>";

                    }

                    parsed.forEach(function (item) {
                        tokens = item.split(' ');
                        name = tokens[0];
                        description = tokens.slice(1).join(" ");
                        buffer += "<li class='composer-result-item'><span class='name' style='text-decoration: underline'>" + name + "</span> &nbsp;<span class='description'>" + description + "</span></li>";
                    });
                    $('.above-results').html("Click in one of the results to select it !");
                    $('.composer-search-results-container').html("<p style='font-weight: bold'>" + parsed.length + " results</p>"
                        + "<ul>" + buffer + "</ul>");

                    // click on the search results from composer
                    $('.composer-result-item .name').on('click', function (evt) {
                        var span = evt.target;
                        var clickedItem = span.innerHTML;
                        console.log(clickedItem);
                        var project_to_check = $('#search-composer-dialog').data('project_to_check');
                        $('.above-results').html("Selected " + clickedItem + " to add! <A href='?page=wpcm&action=require&path=" + project_to_check + "&package=" + clickedItem + "'>Click here to require it</A>");
                    });
                });
        });

    }); // end of document.load

    function dialogComposerOutput() {
        $('#composer-output-dialog')
            .css({overflow: 'scroll', 'overflow-wrap': 'break-word', 'max-height': '400px'})
            .dialog({
                width: "600px",
                buttons: [
                    {
                        text: "Ok",
                        icon: "ui-icon-heart",
                        click: function () {
                            $(this).dialog("close");
                        }
                    }
                ]
            });
        $('#composer-output-dialog').css({'max-height': '400px'});
        window.scrollTo(0, 0);
    }

    function grabQueryPairsFromAnchor(anchor) {
        href = $(anchor).attr('href');
        urlTokens = href.split('?');
        arrayPairs = parseQuery(urlTokens[1]);
        return arrayPairs;
    }

    function viewComposerJsonOnProject(anchor) {
        arrayPairs = grabQueryPairsFromAnchor(anchor);
        //console.log(arrayPairs);
        jQuery.get(ajaxurl + '?action=view_composer_json',
            {project_to_check: arrayPairs['project_to_check']},
            function (contents) {
                console.log(contents);
                $('#composerjson-view-dialog .contents .wrapped').text(contents);
                $('#composerjson-view-dialog').dialog({width: "600px", minHeight: "400px"}).css({overflow: 'scroll'});
            });
    }

    function waitingForComposerDialog() {
        jQuery('#dialog-message .reticences').append('.');
    }

    function ajax_query_composer_output() {
        jQuery.get(ajaxurl + '?action=query_composer_launcher_output',
            { // empty arguments
            },
            function ($composer_out) {
                jQuery('#composerOutput').append($composer_out + "\n");
            });

    }

    function parseQuery(queryString) {
        var query = {};
        var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
        }
        return query;
    }


</script>
