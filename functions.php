<?php

/**
*
* Get a variable from a file and return its value; if not there, return the default.
* NOTE: The variable's value is anything after the equal sign, so there shouldn't be a comment on the line.
* NOTE: There may be something before $searchfor, e.g., "export X=1", where "X" is $searchfor.
*/
function get_variable($file, $searchfor, $default)
{
	// get the file contents
	$contents = file_get_contents($file);
	if ("$contents" == "") return($default);	// file not found or not readable

	// escape special characters in the query
	$pattern = preg_quote($searchfor, '/');
	// finalise the regular expression, matching the whole line
	$pattern = "/^.*$pattern.*\$/m";

	// search, and store all matching occurences in $matches, but only return the last one
	$num_matches = preg_match_all($pattern, $contents, $matches);
	if ($num_matches) {
		$double_quote = '"';

		// Format: [stuff]$searchfor=$value   or   [stuff]$searchfor="$value"
		// Need to delete  [stuff]$searchfor=  and optional double quotes
		$last = $matches[0][$num_matches - 1];	// get the last one
		$last = explode( '=', $last)[1];	// get everything after equal sign
		$last = str_replace($double_quote, "", $last);
		return($last);
	} else {
		return($default);
	}
}

$displayed_thumbnail_error_message = false;
function make_thumb($src, $dest, $desired_width)
{
 	/* Make sure the imagecreatefromjpeg() function is in PHP. */
	global $displayed_thumbnail_error_message;
	if (function_exists('imagecreatefromjpeg') == false)
	{
		if ($displayed_thumbnail_error_message == false)
		{
			echo "<br><p style='color: red'>Unable to make thumbnail(s); imagecreatefromjpeg() does not exist.<br>If you do NOT have the file '/etc/php/7.3/mods-available/gd.ini' you need to download the latest PHP.</p>";
			$displayed_thumbnail_error_message = true;
		}
		return(false);
	}

	/* read the source image */
	$source_image = imagecreatefromjpeg($src);
	$width = imagesx($source_image);
	$height = imagesy($source_image);

	/* find the "desired height" of this thumbnail, relative to the desired width  */
	$desired_height = floor($height * ($desired_width / $width));

	/* create a new, "virtual" image */
	$virtual_image = imagecreatetruecolor($desired_width, $desired_height);

	/* copy source image at a resized size */
	imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);

	/* create the physical thumbnail image to its destination */
 	imagejpeg($virtual_image, $dest);

	if (file_exists($dest)) {
		return(true);
	} else {
		echo "<p>Unable to create thumbnail for '$src'.</p>";
		print_r(error_get_last());
		return(false);
	}
}

// Similar to make_thumb() but using a video for the input file.
function make_thumb_from_video($src, $dest, $desired_width)
{
	// start 5 seconds in to skip any auto-exposure changes at the beginning.  This of course assumes the video is at least 5 sec long.
	// "-1" scales the height to the original aspect ratio.
	exec("ffmpeg -ss 00:00:05 -i '$src' -frames:v 1 -filter:v scale='$desired_width:-1' -frames:v 1 '$dest'");
	if (file_exists($dest)) return(true);
	else return(false);
}

// Display thumbnails with links to the full-size files
// for startrails, keograms, and videos.
// The function to make thumbnails for videos is different
function display_thumbnails($image_type)
{
	$image_type_len = strlen($image_type);
	if ($image_type == "allsky") {
		$ext = "mp4";
	} else {
		$ext = "jpg";
	}

	$files = array();
	if ($handle = opendir('.')) {
		while (false !== ($entry = readdir($handle))) {
			if (strpos($entry, $ext) !== false) {
				$files[] = $entry;
			}
		}
		closedir($handle);
	}

	asort($files);
	
	if (! is_dir('thumbnails')) {
		if (! mkdir('thumbnails', 0755))
			echo "<p>Unable to make 'thumbnails' directory. You will need to create it manually.</p>";
			print_r(error_get_last());
	}

	echo "<a class='back-button' href='..'><i class='fa fa-chevron-left'></i>Back to Live View</a>";
	echo "<div class=archived-videos>";

	$thumbnailSizeX = get_variable(ALLSKY_CONFIG .'/config.sh', 'THUMBNAILSIZE_X=', '100');
	foreach ($files as $file) {
		// The thumbnail should be a .jpg.
		$thumbnail = str_replace(".mp4", ".jpg", "thumbnails/$file");
		if (! file_exists($thumbnail)) {
			if ($image_type == "allsky") {
				if (! make_thumb_from_video($file, $thumbnail, thumbnailSizeX)) {
					// We can't use the video file as a thumbnail
					$thumbnail = "../NoThumbnail.png";
				}
			} else {
				if (! make_thumb($file, $thumbnail, thumbnailSizeX)) {
					// Using the full-sized file as a thumbnail is overkill,
					// but it's better than no thumbnail.
					$thumbnail = "./$file";
				}
			}
		}
		$year = substr($file, $image_type_len + 1, 4);
		$month = substr($file, $image_type_len + 5, 2);
		$day = substr($file, $image_type_len + 7, 2);
		$date = $year.$month.$day;
		echo "<a href='./$file'><div class='day-container'><div class='image-container'><img id=".$date." src='$thumbnail' title='$image_type-$year-$month-$day'/></div><div class='day-text'>$year-$month-$day</div></div></a>";
	}
	echo "</div>";
}
?>
