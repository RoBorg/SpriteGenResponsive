<?php

require_once 'include/TransparentPngCreator.php';
require_once 'include/ResponsiveSprite.php';

ini_set('memory_limit', '512M');

$done = false;
$errors = [
    'images' => '',
    'sprite' => '',
];

if (!empty($_POST['save'])) {
    if (empty($_FILES['images']) || !is_array($_FILES['images']['name']) || (count($_FILES['images']['name']) < 2)) {
        $errors['images'] = 'Please select at least 2 image files';
    } else {
        $images = [];
        $imageErrors = [];

        foreach ($_FILES['images']['name'] as $i => $name) {
            switch ($_FILES['images']['error'][$i]) {
                case UPLOAD_ERR_OK:
                    $images[] = [
                        'name' => $_FILES['images']['name'][$i],
                        'type' => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    ];
                    break;

                case UPLOAD_ERR_INI_SIZE:
                    $imageErrors[] = $name . ': The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                    break;

                case UPLOAD_ERR_FORM_SIZE:
                    $imageErrors[] = $name . ': The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                    break;

                case UPLOAD_ERR_PARTIAL:
                    $imageErrors[] = $name . ': The uploaded file was only partially uploaded.';
                    break;

                case UPLOAD_ERR_NO_FILE:
                    break;

                case UPLOAD_ERR_NO_TMP_DIR:
                    $imageErrors[] = $name . ': Missing a temporary folder.';
                    break;

                case UPLOAD_ERR_CANT_WRITE:
                    $imageErrors[] = $name . ': Failed to write file to disk.';
                    break;

                case UPLOAD_ERR_EXTENSION:
                    $imageErrors[] = $name . ': File upload stopped by extension.';
                    break;

                default:
                    throw new Exception('Unexpected file error number "' . $_FILES['images']['error'][$i] . '"');
            }
        }

        if (empty($imageErrors)) {
            if (count($images) < 2) {
                $errors['images'] = 'Please select at least 2 image files';
            } else {
                $options = [
                    'output_type' => empty($_POST['output_type']) ? 'png' : $_POST['output_type'],
                    'jpeg_quality' => !isset($_POST['jpeg_quality']) ? 75 : (int)$_POST['jpeg_quality'],
                    'css_prefix' => empty($_POST['css_prefix']) ? '' : $_POST['css_prefix'],
                    'padding' => empty($_POST['padding']) ? 0 : (int)$_POST['padding'],
                    'jpeg_reduce_artefacts' => !empty($_POST['jpeg_reduce_artefacts']),
                ];

                $sprite = new ResponsiveSprite();

                try {
                    $sprite->run($images, $options);
                    $done = true;
                } catch (Exception $e) {
                    $errors['sprite'] = 'There was an error creating your sprite: ' . $e->getMessage();
                }
            }
        } else {
            $errors['images'] = implode(' ', $imageErrors);
        }
    }
}

if ($done) {
    $spriteDataUri = $sprite->getDataUri();
}

?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Responsive CSS Sprite Generator</title>
        <link rel="shortcut icon" href="/favicon.ico">
        <link type="text/css" rel="stylesheet" href="assets/syntaxhighlighter/styles/shCoreDefault.css">
        <link type="text/css" rel="stylesheet" href="assets/style.css">

        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shCore.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shBrushCss.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shBrushXml.js"></script>
        <script type="text/javascript">
            SyntaxHighlighter.defaults['quick-code'] = false;
            SyntaxHighlighter.all();

            (function($)
            {
                $(function()
                {
                    $('#tabs').on('click', 'li', function()
                    {
                        $('#tabs li.selected').removeClass('selected');
                        $(this).addClass('selected');

                        $('div.tab.selected').removeClass('selected');
                        $($(this).find('a').attr('href')).addClass('selected');

                        return false;
                    });
                });
            })(jQuery);
        </script>
        <?php

        if ($done) {
            print('<style type="text/css">' . $sprite->getCss($spriteDataUri) . '</style>');
        }

        ?>
    </head>
    <body>
        <div id="header">
            <div id="header-inner">
                <h1>
                    <a href="http://<?=htmlentities($_SERVER['HTTP_HOST']);?>">Responsive CSS Sprite Generator</a>
                </h1>
                <p>
                    Optimize your website using CSS Sprites!
                    Generate CSS Sprites to speed up your website by reducing HTTP requests.
                </p>
            </div>
        </div>
        <div id="page">
            <ul id="tabs">
                <li class="selected">
                    <a href="#sprites">Responsive CSS Sprites</a>
                </li>
                <li>
                    <a href="#about">About</a>
                </li>
                <li>
                    <a href="#faq">FAQ</a>
                </li>
                <li>
                    <a href="#news">News</a>
                </li>
            </ul>
            <div class="tab selected" id="sprites">
                <?php

                if ($done) {
                    ?>
                    <h2>Your Image</h2>
                    <p>Right-click to save this image.</p>
                    <img src="<?=htmlspecialchars($spriteDataUri); ?>" alt="" id="sprite">

                    <h2>Your CSS</h2>
                    <p>Save this to your CSS file.</p>
                    <pre class="brush: css;"><?=htmlspecialchars($sprite->getCss()); ?></pre>

                    <h2>Your HTML</h2>
                    <p>Use this to insert your sprite images.</p>
                    <pre class="brush: xml;"><?=htmlspecialchars($sprite->getHtml()); ?></pre>

                    <h2>Demo</h2>
                    <p>Here's what it your images will look like - try resizing your browser to see it in action.</p>
                    <div id="demo">
                        <?=$sprite->getHtml(); ?>
                    </div>

                    <h2>New Sprite</h2>
                    <p><a href="">Start a new sprite</a></p>
                    <?php

                } else {
                    if ($errors['sprite']) {
                        ?>
                        <p class="error first"><?=htmlspecialchars($errors['sprite']); ?></p>
                        <?php

                    } ?>
                    <form method="post" enctype="multipart/form-data">
                        <h2>Responsive CSS Sprites</h2>
                        <p>
                            <b>CSS sprites</b> allow you to combine multiple images into a single file.
                            This reduces the number of HTTP requests, speeding up page loading.
                            Ordinary sprites are a fixed size, but <b>responsive</b> sprites are able to be resized, for example using
                            <code>max-width: 100%;</code>
                        </p>
                        <p>If you don't need your sprites to be responsive, you're better off using a normal <a href="http://css.spritegen.com">CSS Sprite Generator</a>.</p>
                        <h2>1: Upload Your Images</h2>
                        <input type="file" name="images[]" multiple="multiple">
                        <p class="error"><?=htmlspecialchars($errors['images']); ?></p>
                        <p class="note">Select up to <?=ini_get('max_file_uploads'); ?> files, total <?=ini_get('upload_max_filesize'); ?>B.</p>

                        <h2>2: Choose Options</h2>
                        <label>
                            Output Type:
                            <select name="output_type" id="output_type" onchange="document.getElementById('jpeg-settings').style.display = this.options[this.selectedIndex].value === 'jpeg' ? 'block' : 'none';">
                                <option value="png">PNG - Recommended</option>
                                <option value="jpeg">JPEG</option>
                                <option value="gif">GIF</option>
                            </select>
                        </label>

                        <fieldset id="jpeg-settings">
                            <legend>JPEG Settings</legend>
                            <label>
                                JPEG Artefact Removal:
                                <input type="checkbox" name="jpeg_reduce_artefacts" id="jpeg_reduce_artefacts">
                            </label>
                            <label>
                                JPEG Quality:
                                <input type="range" name="jpeg_quality" id="jpeg_quality" min="0" max="100" value="75" step="1">
                            </label>
                        </fieldset>

                        <fieldset id="other-settings">
                            <legend>Other Settings</legend>
                            <label>
                                CSS Class Prefix:
                                <input type="text" name="css_prefix" id="css_prefix">
                            </label>
                            <label>
                                Padding between images:
                                <select name="padding" id="padding">
                                    <option value="0" selected="selected">0px</option>
                                    <option value="1">1px</option>
                                    <option value="2">2px</option>
                                    <option value="3">3px</option>
                                    <option value="4">4px</option>
                                    <option value="5">5px</option>
                                    <option value="10">10px</option>
                                    <option value="20">20px</option>
                                </select>
                                (This will make your file slightly larger but can prevent images bleeding into each other)
                            </label>
                        </fieldset>

                        <h2>3: Create Your Sprite</h2>
                        <input type="hidden" name="save" value="1">
                        <input type="submit" value="Create Sprite">
                    </form>
                    <?php

                }

                ?>
            </div>
            <div class="tab" id="about">
                <h2>About</h2>
                By <a href="http://twitter.com/RoBorg" target="_blank">RoBorg</a>

                <h3>What is a CSS Sprite?</h3>
                <p>
                    A CSS sprite is a single image file that contains multiple individual images.
                    You can use sprites to make your websites load faster, by decreasing the number of HTTP requests your users have to make.
                    Each request will contain the overhead of HTTP headers (including cookies) and the connection's latency.
                    By using a single image file instead of many, you can dramatically decrease the time it take your pages to load.
                </p>

                <h3>What does Responsive mean?</h3>
                <p>
                    There are a few definitions, but in this case we mean that the image is able to be resized to fit the screen, for example using <code>max-width: 100%;</code>.
                    Normally this doesn't work with CSS sprites, but the technique on this page allows it.
                </p>

                <h3>What do I get and how do I use it?</h3>
                <p>This tool generates:</p>
                <ul>
                    <li>An image file</li>
                    <li>A block of CSS code</li>
                    <li>An &lt;img&gt; tag for each image</li>
                </ul>
                <p>
                    First upload the image file and add the CSS to your stylesheet.
                    Then replace your images with &lt;img&gt; tag to load the sprite.
                    CSS classes are generated from the image filenames you upload, so for example:
                    <code>&lt;img src="icon.png"&gt;</code>
                    might become
                    <code>&lt;img class="icon" alt="" src="data:..."&gt;</code>
                </p>
            </div>
            <div class="tab" id="faq">
                <h2>Frequently Asked Questions</h2>

                <h3>Who wrote this?</h3>
                <p>
                    Greg, AKA <a href="http://www.roborg.co.uk/" target="_blank">RoBorg</a> did - I'm a professional PHP programmer for <a href="http://www.justsayplease.co.uk/">Just Say Please</a>.<br>
                    You can <a href="http://twitter.com/RoBorg" target="_blank">follow me on Twitter</a>
                </p>
                <p>
                    <a href="http://stackoverflow.com/users/24181/greg">
                        <img src="http://stackoverflow.com/users/flair/24181.png?theme=clean" width="208" height="58" alt="profile for Greg at Stack Overflow, Q&amp;A for professional and enthusiast programmers" title="profile for Greg at Stack Overflow, Q&amp;A for professional and enthusiast programmers">
                    </a>
                </p>

                <h3>How do I report a bug?</h2>
                <p>At the moment just <a href="http://twitter.com/RoBorg" target="_blank">via Twitter</a>.</p>

                <h3>How long do you store my source images and sprite for?</h3>
                <p>They're not stored on the server.</p>

                <h3>Are images I upload private?</h3>
                <p>Yes.</p>

                <h3>Is there an API?</h3>
                <p>Not at the moment. Tweet me if you're interested.</p>

                <h3>Is this project open source</h3>
                <p>Not at the moment, but if I receive enough interest I might clean up the code and release it.</p>

                <h3>How is this website written?</h3>
                <p>The sprite generator is written in PHP, using the GD image functions. The transparent PNGs are manually generated.</p>

                <h3>What's all that suspicious-looking "data:" stuff?</h3>
                <p>That's a base-64 encoded transparent PNG file, the size of the orignal image. This is used to get the aspect ratio right.</p>
            </div>
            <div class="tab" id="news">
                <h2>Latest News</h2>

                <h3>May 2014</h3>
                <ul>
                    <li>Improved error handling</li>
                    <li>Increased memory limit</li>
                </ul>

                <h3>Jan 2014</h3>
                <ul>
                    <li>Invented responsive CSS sprites!</li>
                </ul>
            </div>
        </div>
        <div id="footer"></div>
        <script type="text/javascript">
            (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
            })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

            ga('create', 'UA-280848-9', 'spritegen.com');
            ga('send', 'pageview');
        </script>
    </body>
</html>
