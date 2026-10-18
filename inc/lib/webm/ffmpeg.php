<?php
/*
* ffmpeg.php
* A barebones ffmpeg based webm implementation for vichan.
*/
function get_webm_info($filename) {
  global $board, $config;
  $filename_arg = escapeshellarg($filename);
  $ffprobe = escapeshellcmd($config['webm']['ffprobe_path']);
  $ffprobe_out = array();
  $webminfo = array();
  exec("$ffprobe -v quiet -print_format json -show_format -show_streams $filename_arg", $ffprobe_out);
  $ffprobe_out = json_decode(implode("\n", $ffprobe_out), 1);
  $webminfo['error'] = is_valid_webm($ffprobe_out);
  if (empty($webminfo['error'])) {
    $video_stream = null;
    if (isset($ffprobe_out['streams'])) {
      foreach ($ffprobe_out['streams'] as $stream) {
        if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
          $video_stream = $stream;
          break;
        }
      }
    }
    if ($video_stream === null && isset($ffprobe_out['streams'][0])) {
      $video_stream = $ffprobe_out['streams'][0];
    }
    $webminfo['width'] = isset($video_stream['width']) ? $video_stream['width'] : 0;
    $webminfo['height'] = isset($video_stream['height']) ? $video_stream['height'] : 0;
    $webminfo['duration'] = isset($ffprobe_out['format']['duration']) ? floatval($ffprobe_out['format']['duration']) : 0;
  }
  return $webminfo;
}

function is_valid_webm($ffprobe_out) {
  global $board, $config;
  if (empty($ffprobe_out) || !isset($ffprobe_out['format']) || !isset($ffprobe_out['format']['format_name']))
    return array('code' => 1, 'msg' => $config['error']['genwebmerror']);
  $extension = strtolower(pathinfo($ffprobe_out['format']['filename'], PATHINFO_EXTENSION));
  $format_name = strtolower($ffprobe_out['format']['format_name']);

  if ($extension === 'webm') {
    if (strpos($format_name, 'webm') === false && strpos($format_name, 'matroska') === false)
      return array('code' => 2, 'msg' => $config['error']['invalidwebm']);
  } elseif ($extension === 'mkv') {
    if (strpos($format_name, 'matroska') === false)
      return array('code' => 2, 'msg' => $config['error']['invalidwebm']);
  } elseif ($extension === 'mp4') {
    if (strpos($format_name, 'mp4') === false && strpos($format_name, 'mov') === false)
      return array('code' => 2, 'msg' => $config['error']['invalidwebm']);
  } else {
    return array('code' => 1, 'msg' => $config['error']['genwebmerror']);  
  }

  $has_video = false;
  $audio_count = 0;
  $width = 0;
  $height = 0;
  if (isset($ffprobe_out['streams'])) {
    foreach ($ffprobe_out['streams'] as $stream) {
      if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
        $has_video = true;
        $width = isset($stream['width']) ? $stream['width'] : 0;
        $height = isset($stream['height']) ? $stream['height'] : 0;
        break;
      }
    }
    foreach ($ffprobe_out['streams'] as $stream) {
      if (isset($stream['codec_type']) && $stream['codec_type'] === 'audio') {
        $audio_count++;
      }
    }
  }

  if (!$has_video || $width <= 0 || $height <= 0)
    return array('code' => 2, 'msg' => $config['error']['invalidwebm']);

  if ($audio_count > 0 && !$config['webm']['allow_audio'])
    return array('code' => 3, 'msg' => $config['error']['webmhasaudio']);

  if (!empty($config['webm']['max_length']) && isset($ffprobe_out['format']['duration']) && $ffprobe_out['format']['duration'] > $config['webm']['max_length'])
    return array('code' => 4, 'msg' => sprintf($config['error']['webmtoolong'], $config['webm']['max_length']));
}

function make_webm_thumbnail($filename, $thumbnail, $width, $height, $duration) {
  global $board, $config;
  $filename_arg = escapeshellarg($filename);
  $thumbnailfc = escapeshellarg($thumbnail);
  $width = intval($width);
  $height = intval($height);
  $ffmpeg = escapeshellcmd($config['webm']['ffmpeg_path']);

  // Locate the overlay image
  $watermark_path = file_exists('static/video.png') ? 'static/video.png' : dirname(__FILE__, 4) . '/static/video.png';
  $watermark_arg = escapeshellarg($watermark_path);

  // Overlay size: max 141x141, scaled down proportionally if thumbnail is smaller
  $overlay_size = min(141, $width, $height);
  $filter = sprintf(
    "[1:v]scale=%d:%d[ovr];[0:v]scale=%d:%d[bg];[bg][ovr]overlay=(W-w)/2:(H-h)/2",
    $overlay_size, $overlay_size, $width, $height
  );

  $ret = 0;
  $ffmpeg_out = array();
  // Extract first frame (-ss 0) and overlay static/video.png centered
  $cmd = "$ffmpeg -y -ss 0 -i $filename_arg -i $watermark_arg -filter_complex " . escapeshellarg($filter) . " -v quiet -an -vframes 1 -update 1 -f mjpeg $thumbnailfc 2>&1";
  exec($cmd, $ffmpeg_out, $ret);
  clearstatcache();

  if (!file_exists($thumbnail) || filesize($thumbnail) === 0) {
    // Fallback without watermark in case of unexpected filter issue
    $ret = 0;
    $cmd = "$ffmpeg -y -ss 0 -i $filename_arg -v quiet -an -vframes 1 -update 1 -vf " . escapeshellarg("scale=$width:$height") . " -f mjpeg $thumbnailfc 2>&1";
    exec($cmd, $ffmpeg_out, $ret);
    clearstatcache();
    if (!file_exists($thumbnail) || filesize($thumbnail) === 0) {
      $ret = 1;
    }
  }
  return $ret;
}
