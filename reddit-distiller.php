<?php
    function getSubredditXml($subredditName, $after=null) {
        $c = curl_init("http://www.reddit.com/r/$subredditName.xml" . ($after != null ? "?after=t3_$after" : ""));
        curl_setopt($c, CURLOPT_TIMEOUT, 60);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($c, CURLOPT_HEADER, false);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec($c);
        curl_close($c);
        return $r;
    }

    function scrapeSubreddit($subredditName, $after=null) {
        $scraped = array();
        $scraped['links'] = array();
        $scraped['next'] = null;
        $xml = simplexml_load_string(getSubredditXml($subredditName, $after));
        $descriptions = $xml->xpath('//item/description');
        foreach ($descriptions as $description) {
            // links
            $descriptionXml = simplexml_load_string('<span>' . strval($description) . '</span>'); // hackery
            $linkHref = $descriptionXml->xpath('//a[text()="[link]"]/@href');
            array_push($scraped['links'], strval($linkHref[0]));
            // next
            preg_match('/comments\/(.+?)\//', $description, $matches);
            $scraped['next'] = $matches[1];
        }
        return $scraped;
    }

    function isImageLink($href) {
        return preg_match('/\.(?:jpg|png|gif)$/', $href) == 1;
    }

    function scrapeSubredditImages($subredditName, $after=null) {
        $scraped = scrapeSubreddit($subredditName, $after);
        $scraped['links'] = array_filter($scraped['links'], 'isImageLink');
        return $scraped;
    }
?>

<?php if (!array_key_exists('r', $_GET)) { // the page itself ?>
    <html>
        <head>
            <script src="/js/jquery-1.4.2.min.js" type="text/javascript" charset="utf-8"></script> <!--todo-->
        </head>
        <body>
            <form action="javascript:hitSelf();">
                <label>Subreddit Name<input type="text" id="r" value="pics" /></label>
                <input type="submit" value="DO IT"/>
            </form>
            <div id="content"></div>
        </body>
        <script>
            var hitInProgress = false;
            var after = null;

            var hitSelf = function() {
                if (!hitInProgress) {
                    hitInProgress = true;
                    $.ajax({
                        url: '<?php print $_SERVER['SCRIPT_NAME']; ?>',
                        type: 'GET',
                        data: 'r=' + $('#r').val() + (after != null ? ('&a=' + after) : ''),
                        success: function(response) {
                            var json = jQuery.parseJSON(response);
                            var links = json.links;
                            for (x in links) {
                                $('#content').append('<img src="' + links[x] + '" />');
                            }
                            after = json.next;
                            hitInProgress = false;
                        }
                    });
                }
            };

            $(window).scroll(function() {
                if ($(window).scrollTop() + $(window).height() >= 0.75 * $(document).height()) { // seen >= 75% of doc
                    hitSelf();
                }
            });
        </script>
    </html>
<?php
    } else { // return da pictures
        print json_encode(scrapeSubredditImages($_GET['r'], array_key_exists('a', $_GET) ? $_GET['a'] : null));
    }
?>
