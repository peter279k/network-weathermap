<?php

require_once "WeatherMapUIBase.class.php";

class WeatherMapCactiUserPlugin extends WeatherMapUIBase
{
    public $config;
    public $colours;

    private $outputDirectory;
    private $imageFormat;

    public $commands = array(
        'viewthumb' => array('handler' => 'handleBigThumb', 'args' => array(array("id", "hash"))),
        'viewthumb48' => array('handler' => 'handleLittleThumb', 'args' => array(array("id", "hash"))),
        'viewimage' => array('handler' => 'handleImage', 'args' => array(array("id", "hash"))),
        'viewmap' => array('handler' => 'handleViewCycle', 'args' => array(array("id", "hash"))),
        'viewmapcycle' => array(
            'handler' => 'handleView', 'args' => array(
                array("fullscreen", "int", true),
                array("group", "int", true)
            )),
        ':: DEFAULT ::' => array(
            'handler' => 'handleMainView',
            'args' => array(
                array("group_id", "int", true)
            )
        )
    );

    public function __construct($config, $colours, $imageFormat)
    {
        $this->colours = $colours;
        $this->config = $config;

        $this->outputDirectory = realpath(dirname(__FILE__) . '/../output/');
        $this->imageFormat = $imageFormat;
    }

    /**
     * @param $request
     * @internal param $config
     */
    public function handleMainView($request, $appObject)
    {
        global $config;

        require_once $config["base_path"] . "/include/top_graph_header.php";
        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $group_id = -1;
        if (isset($request['group_id'])) {
            $group_id = $request['group_id'];
            $_SESSION['wm_last_group'] = $group_id;
        } else {
            if (isset($_SESSION['wm_last_group'])) {
                $group_id = intval($_SESSION['wm_last_group']);
            }
        }

        $tabs = $this->getValidTabs();
        $tab_ids = array_keys($tabs);

        if (($group_id == -1) && (sizeof($tab_ids) > 0)) {
            $group_id = $tab_ids[0];
        }

        $pageStyle = read_config_option("weathermap_pagestyle");
        if ($pageStyle == 0) {
            $this->wmuiThumbnailView($group_id);
        }

        if ($pageStyle == 1) {
            $this->drawFullMapView(false, false, $group_id);
        }

        if ($pageStyle == 2) {
            $this->drawFullMapView(false, true, $group_id);
        }

        $this->outputVersionBox();
        require_once($config["base_path"] . "/include/bottom_footer.php");
    }

    public function handleView($request, $appObject)
    {
        global $config;

        require_once $config["base_path"] . "/include/top_graph_header.php";
        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $mapID = $this->deduceMapID($request);

        if ($mapID >= 0) {
            $this->wmuiSingleMapView($mapID);
        }

        $this->outputVersionBox();

        require_once $config["base_path"] . "/include/bottom_footer.php";
    }

    /**
     * @param $request
     * @return int
     */
    public function deduceMapID($request)
    {
        $mapID = -1;

        if (isset($request['id']) && (!is_numeric($request['id']) || strlen($request['id']) == 20)) {
            $mapID = $this->translateHashToID($request['id']);
        }

        if (isset($request['id']) && is_numeric($request['id'])) {
            $mapID = intval($request['id']);
            return $mapID;
        }
        return $mapID;
    }

    public function handleViewCycle($request, $appObject)
    {
        global $config;

        $fullscreen = false;
        if ((isset($request['fullscreen']) && is_numeric($request['fullscreen']))) {
            if (intval($request['fullscreen']) == 1) {
                $fullscreen = true;
            }
        }

        if ($fullscreen === true) {
            print "<!DOCTYPE html>\n";
            print "<html><head>";
            print '<LINK rel="stylesheet" type="text/css" media="screen" href="cacti-resources/weathermap.css">';
            print "</head><body id='wm_fullscreen'>";
        } else {
            include_once $config["base_path"] . "/include/top_graph_header.php";
        }

        print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
        print "<script type=\"text/javascript\" src=\"vendor/overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

        $groupid = -1;
        if ((isset($request['group']) && is_numeric($request['group']))) {
            $groupid = intval($request['group']);
        }

        $this->drawFullMapView(true, false, $groupid, $fullscreen);

        if ($fullscreen === true) {
            print "</body></html>";
        } else {
            $this->outputVersionBox();
            include_once $config["base_path"] . "/include/bottom_footer.php";
        }
    }

    public function handleImage($request, $appObject)
    {

    }

    public function handleBigThumb($request, $appObject)
    {

    }

    public function handleLittleThumb($request, $appObject)
    {

    }

    /**
     * @param $action
     */
    public function wmuiHandleImageOutput($request, $appObject)
    {
        $mapID = $this->deduceMapID($request);

        if ($mapID < 0) {
            return;
        }
        $userID = $this->getCactiUserID();

        $map = db_fetch_assoc("select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and (userid=" . $userID . " or userid=0) and  active='on' and weathermap_maps.id=" . $mapID . " LIMIT 1");

        if (sizeof($map) != 1) {
            // in the management view, a disabled map will fail the query above, so generate *something*
            header('Content-type: image/png');
            $this->outputGreyPNG(48, 48);
        }

        $insert = ".";
        if ($action == 'viewthumb') {
            $insert = ".thumb.";
        }

        if ($action == 'viewthumb48') {
            $insert = ".thumb48.";
        }

        $imageFileName = $this->outputDirectory . '/' . $map[0]['filehash'] . $insert . $this->imageFormat;

        header('Content-type: image/png');

        if (file_exists($imageFileName)) {
            readfile($imageFileName);
        } else {
            $this->outputGreyPNG(48, 48);
        }
    }

    public function wmuiSingleMapView($mapid)
    {
        global $colors;

        $is_wm_admin = false;

        $outputDirectory = $this->outputDirectory;

        $userid = wmGetCactiUserID();
        $map = db_fetch_assoc("select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $mapid);

        if (sizeof($map)!=1) {
            return;
        }

        print do_hook_function('weathermap_page_top', '');

        $htmlFileName = $outputDirectory . DIRECTORY_SEPARATOR . $map[0]['filehash'] . ".html";
        $mapTitle = ($map[0]['titlecache'] == "" ? "Map for config file: " . $map[0]['configfile'] : $map[0]['titlecache']);

        wmGenerateMapSelectorBox($mapid);

        html_graph_start_box(1, true);
        ?>
        <tr bgcolor="<?php print $colors["panel"]; ?>">
            <td>
                <table width="100%" cellpadding="0"
                       cellspacing="0">
                    <tr>
                        <td class="textHeader"
                            nowrap><?php print $mapTitle;

                            if ($is_wm_admin) {
                                print "<span style='font-size: 80%'>";
                                print "[ <a href='weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid . "'>Map Settings</a> |";
                                print "<a href='weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=" . $mapid . "'>Map Permissions</a> |";
                                print "<a href=''>Edit Map</a> ]";
                                print "</span>";
                            }

                            ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr><td>
        <?php

        if (file_exists($htmlFileName)) {
            include $htmlFileName;
        } else {
            print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.";

            global $user_auth_realm_filenames;
            $realm_id2 = 0;

            if (isset($user_auth_realm_filenames[basename('weathermap-cacti-plugin.php')])) {
                $realm_id2 = $user_auth_realm_filenames[basename('weathermap-cacti-plugin.php')];
            }

            $userid = $this->getCactiUserID();

            if ((db_fetch_assoc(
                "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $userid . "' and user_auth_realm.realm_id='$realm_id2'"
            )) || (empty($realm_id2))
            ) {
                print " (If this message stays here for more than one poller cycle, then check your cacti.log file for errors!)";
            }
            print "</em></div>";
        }
        print "</td></tr>";
        html_graph_end_box();
    }

    public function wmuiThumbnailView($limitToGroup = -1)
    {
        $mapList = $this->getAuthorisedMaps($limitToGroup);
        $mapCount = sizeof($mapList);

        // if there's only one map, ignore the thumbnail setting and show it full size
        if ($mapCount == 1) {
            $this->drawFullMapView(false, false, $limitToGroup, false);
            return;
        }

        if ($mapCount > 1) {
            $this->drawThumbnailView($mapList, $limitToGroup);
            return;
        }

        print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em></div>\n";
    }

    public function drawFullMapView($cycle = false, $firstOnly = false, $limitToGroup = -1, $showFullscreen = false)
    {
        $colors = $this->colours;

        $_SESSION['custom'] = false;
        $mapList = $this->getAuthorisedMaps($limitToGroup);

        if ($firstOnly) {
            $mapList = array($mapList[0]);
        }

        if (sizeof($mapList) == 1) {
            $pageTitle = "Network Weathermap";
        } else {
            $pageTitle = "Network Weathermaps";
        }

        $class = "";
        if ($cycle) {
            $class = "inplace";
        }
        if ($showFullscreen) {
            $class = "fullscreen";
        }

        if ($cycle) {
            print "<script src='vendor/jquery/dist/jquery.min.js'></script>";
            print "<script src='vendor/jquery-idletimer/dist/idle-timer.min.js'></script>";
            $extra = "";
            if ($limitToGroup > 0) {
                $extra = " in this group";
            }
            ?>
            <div id="wmcyclecontrolbox" class="<?php print $class ?>">
                <div id="wm_progress"></div>
                <div id="wm_cyclecontrols">
                    <a id="cycle_stop" href="?action="><img border="0" src="cacti-resources/img/control_stop_blue.png"
                                                            width="16" height="16"/></a>
                    <a id="cycle_prev" href="#"><img border="0" src="cacti-resources/img/control_rewind_blue.png"
                                                     width="16" height="16"/></a>
                    <a id="cycle_pause" href="#"><img border="0" src="cacti-resources/img/control_pause_blue.png"
                                                      width="16" height="16"/></a>
                    <a id="cycle_next" href="#"><img border="0" src="cacti-resources/img/control_fastforward_blue.png"
                                                     width="16" height="16"/></a>
                    <a id="cycle_fullscreen"
                       href="?action=viewmapcycle&fullscreen=1&group=<?php echo $limitToGroup; ?>"><img border="0"
                                                                                                          src="cacti-resources/img/arrow_out.png"
                                                                                                          width="16"
                                                                                                          height="16"/></a>
                    Showing <span id="wm_current_map">1</span> of <span id="wm_total_map">1</span>.
                    Cycling all available maps<?php echo $extra; ?>.
                </div>
            </div>
            <?php
        }

        // only draw the whole screen if we're not cycling, or we're cycling without fullscreen mode
        if ($cycle === false || $showFullscreen === false) {
            html_graph_start_box(2, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>">
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="textHeader" nowrap> <?php print $pageTitle; ?> </td>
                            <td align="right">
                                <?php
                                if (!$cycle) {
                                    ?>
                                    (automatically cycle between full-size maps (<?php

                                    if ($limitToGroup > 0) {
                                        print '<a href = "?action=viewmapcycle&group=' . intval($limitToGroup)
                                            . '">within this group</a>, or ';
                                    }
                                    print ' <a href = "?action=viewmapcycle">all maps</a>';
                                    ?>)

                                    <?php
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php
            html_graph_end_box();

            $this->generateGroupTabs($limitToGroup);
        }

        if (sizeof($mapList) > 0) {
            print "<div class='all_map_holder $class'>";

            foreach ($mapList as $map) {
                $htmlfile = $this->outputDirectory . DIRECTORY_SEPARATOR . $map['filehash'] . ".html";
                $maptitle = $map['titlecache'];
                if ($maptitle == '') {
                    $maptitle = "Map for config file: " . $map['configfile'];
                }

                print '<div class="weathermapholder" id="mapholder_' . $map['filehash'] . '">';
                if ($cycle === false || $showFullscreen === false) {
                    html_graph_start_box(1, true);

                    ?>
                    <tr bgcolor="#<?php echo $colors["header_panel"] ?>">
                <td colspan="3">
                    <table width="100%" cellspacing="0" cellpadding="3" border="0">
                        <tr>
                            <td align="left" class="textHeaderDark">
                                <a name="map_<?php echo $map['filehash']; ?>">
                                </a><?php print htmlspecialchars($maptitle); ?>
                            </td>
                        </tr>
                    </table>
                </td>
                </tr>
                <tr>
                <td>
                <?php
                }

                if (file_exists($htmlfile)) {
                    include($htmlfile);
                } else {
                    print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.</em></div>";
                }


                if ($cycle === false || $showFullscreen === false) {
                    print '</td></tr>';
                    html_graph_end_box();
                }
                print '</div>';
            }
            print "</div>";

            if ($cycle) {
                $refreshTime = read_config_option("weathermap_cycle_refresh");
                $pollerInterval = read_config_option("poller_interval"); ?>
                <script type="text/javascript" src="cacti-resources/map-cycle.js"></script>
                <script type="text/javascript">
                    $(document).ready(public function () {
                        WMcycler.start({
                            fullscreen: <?php echo ($showFullscreen ? "1" : "0"); ?>,
                            poller_cycle: <?php echo $pollerInterval * 1000; ?>,
                            period: <?php echo $refreshTime  * 1000; ?>
                        });
                    });
                </script>
                <?php
            }
        } else {
            print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em></div>\n";
        }
    }

    public function translateHashToID($idname)
    {
        $SQL = "select id from weathermap_maps where configfile='" . mysql_real_escape_string($idname)
            . "' or filehash='" . mysql_real_escape_string($idname) . "'";
        $map = db_fetch_assoc($SQL);

        return $map[0]['id'];
    }

    public function outputVersionBox()
    {
        global $WEATHERMAP_VERSION, $colors;
        global $user_auth_realm_filenames;

        $pagefoot = "Powered by <a href=\"http://www.network-weathermap.com/?v=$WEATHERMAP_VERSION\">"
            . "PHP Weathermap version $WEATHERMAP_VERSION</a>";

        $realm_id2 = 0;

        if (isset($user_auth_realm_filenames['weathermap-cacti-plugin-mgmt.php'])) {
            $realm_id2 = $user_auth_realm_filenames['weathermap-cacti-plugin-mgmt.php'];
        }
        $userid = $this->getCactiUserID();

        if ((db_fetch_assoc(
            "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='"
            . $userid . "' and user_auth_realm.realm_id='$realm_id2'"
        )) || (empty($realm_id2))
        ) {
            $pagefoot .= " --- <a href='weathermap-cacti-plugin-mgmt.php' title='Go to the map management page'>";
            $pagefoot .= "Weathermap Management</a> | <a target=\"_blank\" href=\"docs/\">Local Documentation</a>";
            $pagefoot .= " | <a target=\"_blank\" href=\"editor.php\">Editor</a>";
        }


        html_graph_start_box(1, true);
        ?>
        <tr bgcolor="<?php print $colors["panel"];?>">
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="textHeader" nowrap> <?php print $pagefoot; ?> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php
        html_graph_end_box();
    }


    public function streamBinaryFile($filename)
    {
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk

        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            $buffer = fread($handle, $chunksize);
            echo $buffer;
        }
        $status = fclose($handle);
        return $status;
    }

    public function generateMapSelectorBox($current_id = 0)
    {
        global $colors;

        $show_selector = intval(read_config_option("weathermap_map_selector"));

        if ($show_selector == 0) {
            return false;
        }

        $userid = $this->getCactiUserID();
        $maps = db_fetch_assoc("select distinct weathermap_maps.*,weathermap_groups.name, weathermap_groups.sortorder as gsort from weathermap_groups,weathermap_auth,weathermap_maps where weathermap_maps.group_id=weathermap_groups.id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by gsort, sortorder");

        if (sizeof($maps) > 1) {
            /* include graph view filter selector */
            html_graph_start_box(3, true);
            ?>
            <tr bgcolor="<?php print $colors["panel"]; ?>" class="noprint">
                <form name="weathermap_select" method="post" action="">
                    <input name="action" value="viewmap" type="hidden">
                    <td class="noprint">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr class="noprint">
                                <td nowrap style='white-space: nowrap;' width="40">
                                    &nbsp;<strong>Jump To Map:</strong>&nbsp;
                                </td>
                                <td>
                                    <select name="id">
                                        <?php

                                        $ngroups = 0;
                                        $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                        foreach ($maps as $map) {
                                            if ($current_id == $map['id']) {
                                                $nullhash = $map['filehash'];
                                            }
                                            if ($map['name'] != $lastgroup) {
                                                $ngroups++;
                                                $lastgroup = $map['name'];
                                            }
                                        }

                                        $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                        foreach ($maps as $map) {
                                            if ($ngroups > 1 && $map['name'] != $lastgroup) {
                                                print "<option style='font-weight: bold; font-style: italic' value='$nullhash'>" . htmlspecialchars($map['name']) . "</option>";
                                                $lastgroup = $map['name'];
                                            }
                                            print '<option ';
                                            if ($current_id == $map['id']) {
                                                print " SELECTED ";
                                            }
                                            print 'value="' . $map['filehash'] . '">';
                                            // if we're showing group headings, then indent the map names
                                            if ($ngroups > 1) {
                                                print " - ";
                                            }
                                            print htmlspecialchars($map['titlecache']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    &nbsp;<input type="image" src="../../images/button_go.gif" alt="Go"
                                                 border="0" align="absmiddle">
                                </td>
                            </tr>
                        </table>
                    </td>
                </form>
            </tr>
            <?php
            html_graph_end_box(false);
        }

        return true;
    }

    public function getValidTabs()
    {
        $tabs = array();
        $userid = $this->getCactiUserID();
        $maps = db_fetch_assoc("select weathermap_maps.*, weathermap_groups.name as group_name from weathermap_auth,weathermap_maps, weathermap_groups where weathermap_groups.id=weathermap_maps.group_id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by weathermap_groups.sortorder");

        foreach ($maps as $map) {
            $tabs[$map['group_id']] = $map['group_name'];
        }

        return ($tabs);
    }

    /**
     * @return int
     */
    public function getCactiUserID()
    {
        $userid = (isset($_SESSION["sess_user_id"]) ? intval($_SESSION["sess_user_id"]) : 1);

        return $userid;
    }

    public function generateGroupTabs($current_tab)
    {
        $tabs = $this->getValidTabs();

        if (sizeof($tabs) > 1) {
            /* draw the categories tabs on the top of the page */
            print "<p></p><table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

            if (sizeof($tabs) > 0) {
                $show_all = intval(read_config_option("weathermap_all_tab"));
                if ($show_all == 1) {
                    $tabs['-2'] = "All Maps";
                }

                foreach (array_keys($tabs) as $tab_short_name) {
                    print "<td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'")
                        . " nowrap='nowrap' width='" . (strlen($tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
                    <span class='textHeader'><a
                    href='weathermap-cacti-plugin.php?group_id=$tab_short_name'>$tabs[$tab_short_name]</a></span>
                    </td>\n
                    <td width='1'></td>\n";
                }
            }

            print "<td></td>\n</tr></table>\n";

            return (true);
        } else {
            return (false);
        }
    }

    public function outputGreyPNG($w, $h)
    {
        $imageRef = imagecreate($w, $h);
        $shade = 240;
        // The first colour allocated becomes the background colour of the image. No need to fill
        imagecolorallocate($imageRef, $shade, $shade, $shade);
        imagepng($imageRef);
    }

    /**
     * @param $limitToGroup
     * @param $mapList
     */
    private function drawThumbnailView($mapList, $limitToGroup)
    {
        $colors = $this->colours;

        html_graph_start_box(2, true);
        ?>
        <tr bgcolor="<?php print $colors["panel"]; ?>">
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="textHeader" nowrap>Network Weathermaps</td>
                        <td align="right">
                            automatically cycle between full-size maps (<?php
                            if ($limitToGroup > 0) {
                                print '<a href = "?action=viewmapcycle&group=' . intval($limitToGroup) . '">within this group</a>, or ';
                            }
                            print ' <a href = "?action=viewmapcycle">all maps</a>'; ?>)
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td><i>Click on thumbnails for a full view (or you can <a href="?action=viewmapcycle">automatically
                        cycle</a> between full-size maps)</i></td>
        </tr>
        <?php
        html_graph_end_box();
        $showLiveLinks = intval(read_config_option("weathermap_live_view"));

        $this->generateGroupTabs($limitToGroup);
        if (sizeof($mapList) > 0) {
            html_graph_start_box(1, false);
            print "<tr><td class='wm_gallery'>";
            foreach ($mapList as $map) {
                $this->drawOneThumbnail($map, $showLiveLinks);
            }
            print "</td></tr>";
            html_graph_end_box();

        }
    }

    /**
     * @param $limit_to_group
     * @return array
     */
    private function getAuthorisedMaps($limit_to_group)
    {
        $userid = $this->getCactiUserID();
        $maplist_SQL = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";

        if ($limit_to_group > 0) {
            $maplist_SQL .= " weathermap_maps.group_id=" . $limit_to_group . " and ";
        }

        $maplist_SQL .= " (userid=" . $userid . " or userid=0) order by sortorder, id";

        $mapList = db_fetch_assoc($maplist_SQL);

        return $mapList;
    }

    /**
     * @param $mapRecord
     * @param $imageFormat
     * @param $showLiveLinks
     * @param $imageDirectory
     */
    private function drawOneThumbnail($mapRecord, $showLiveLinks)
    {
        $imgSize = "";
        $thumbnailFilename = $this->outputDirectory . DIRECTORY_SEPARATOR .  $mapRecord['filehash'] . ".thumb." . $this->imageFormat;
        $thumbnailImageURL = "?action=viewthumb&id=" . $mapRecord['filehash'] . "&time=" . time();

        if ($mapRecord['thumb_width'] > 0) {
            $imgSize = sprintf(' WIDTH="%d" HEIGHT="%d" ', $mapRecord['thumb_width'], $mapRecord['thumb_height']);
        }
        $mapTitle = $mapRecord['titlecache'];
        if ($mapTitle == '') {
            $mapTitle = "Map for config file: " . $mapRecord['configfile'];
        }
        print '<div class="wm_thumbcontainer" style="margin: 2px; border: 1px solid #bbbbbb; padding: 2px; float:left;">';
        if (file_exists($thumbnailFilename)) {
            print '<div class="wm_thumbtitle" style="font-size: 1.2em; font-weight: bold; text-align: center">' . $mapTitle;
            print '</div><a href="weathermap-cacti-plugin.php?action=viewmap&id=' . $mapRecord['filehash'];
            print '"><img class="wm_thumb" ' . $imgSize . 'src="' . $thumbnailImageURL . '" alt="' . $mapTitle;
            print '" border="0" hspace="5" vspace="5" title="' . $mapTitle . '"/></a>';
        } else {
            print "(thumbnail for map not created yet for $thumbnailFilename)";
        }
        if ($showLiveLinks == 1) {
            print "<a href='?action=liveview&id=" . $mapRecord['filehash'] . "'>(live)</a>";
        }
        print '</div> ';
    }
}