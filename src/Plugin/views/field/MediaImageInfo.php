<?php

namespace Drupal\orbit_media\Plugin\views\field;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides asset metadata details for image and local video media.
 *
 * @category Orbit
 * @package Drupal\orbit_media\Plugin\views\field
 *
 * @ViewsField("media_image_info")
 */
class MediaImageInfo extends FieldPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return void
   *   No return value.
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField(
              $this->tableAlias,
              'mid'
          );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\views\ResultRow $values
   *   The current result row.
   *
   * @return array|string
   *   A render array or fallback text.
   */
  public function render(ResultRow $values): array|string {
    $media = $values->_entity;

    if (!$media instanceof MediaInterface) {
      return $this->t('No entity');
    }

    if ($media->hasField('field_media_image')
          && !$media->get('field_media_image')->isEmpty()
      ) {
      return $this->_buildImageInfo($media);
    }

    if ($media->hasField('field_media_video_file')
          && !$media->get('field_media_video_file')->isEmpty()
      ) {
      return $this->_buildVideoInfo($media);
    }

    return $this->t('N/A');
  }

  /**
   * Builds display details for image media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array|string
   *   A render array or fallback text.
   */
  private function _buildImageInfo(MediaInterface $media): array|string {
    $file = $media->get('field_media_image')->entity;
    if (!$file instanceof FileInterface) {
      return $this->t('N/A');
    }

    $lines = [];
    $item = $media->get('field_media_image')->first();
    if ($item && $item->width && $item->height) {
      $lines[] = (string) $this->t(
                'Dimensions: @w x @h px',
                [
                  '@w' => $item->width,
                  '@h' => $item->height,
                ]
            );
    }

    $lines[] = (string) $this->t(
              'MIME: @type',
              ['@type' => $file->getMimeType()]
          );

    return [
      '#markup' => implode('<br />', $lines),
    ];
  }

  /**
   * Builds display details for locally hosted video media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array|string
   *   A render array or fallback text.
   */
  private function _buildVideoInfo(MediaInterface $media): array|string {
    $file = $media->get('field_media_video_file')->entity;
    if (!$file instanceof FileInterface) {
      return $this->t('N/A');
    }

    $metadata = $this->_getLocalVideoMetadata($file);
    $lines = [
      (string) $this->t(
                      'Size: @size',
                      ['@size' => ByteSizeMarkup::create($file->getSize())]
      ),
    ];

    if (!empty($metadata['width']) && !empty($metadata['height'])) {
      $lines[] = (string) $this->t(
                'Dimensions: @w x @h px',
                [
                  '@w' => $metadata['width'],
                  '@h' => $metadata['height'],
                ]
            );
    }

    if (!empty($metadata['duration'])) {
      $lines[] = (string) $this->t(
                'Duration: @duration',
                ['@duration' => $metadata['duration']]
            );
    }

    $lines[] = (string) $this->t(
              'MIME: @type',
              ['@type' => $file->getMimeType()]
          );

    return [
      '#markup' => implode('<br />', $lines),
    ];
  }

  /**
   * Extracts best-effort metadata from a local MP4 file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return array
   *   Parsed metadata keyed by width, height, and duration.
   */
  private function _getLocalVideoMetadata(FileInterface $file): array {
    $realpath = \Drupal::service('file_system')->realpath(
              $file->getFileUri()
          );
    if (!$realpath || !is_readable($realpath)) {
      return [];
    }

    $handle = fopen($realpath, 'rb');
    if ($handle === FALSE) {
      return [];
    }

    try {
      $filesize = filesize($realpath);
      if ($filesize === FALSE || $filesize === 0) {
        return [];
      }

      $metadata = $this->_parseMp4Container($handle, (int) $filesize);
      if (!empty($metadata['duration_seconds'])) {
        $metadata['duration'] = $this->_formatDuration(
                  (float) $metadata['duration_seconds']
              );
      }

      return $metadata;
    } finally {
      fclose($handle);
    }
  }

  /**
   * Parses MP4 atoms until the requested byte boundary.
   *
   * @param resource $handle
   *   The open file handle.
   * @param int $boundary
   *   The byte offset boundary.
   *
   * @return array
   *   Parsed MP4 metadata.
   */
  private function _parseMp4Container($handle, int $boundary): array {
    $metadata = [];

    while (($position = ftell($handle)) !== FALSE && $position < $boundary) {
      $atom = $this->_readAtomHeader($handle, $boundary);
      if ($atom === NULL) {
        break;
      }

      if ($atom['type'] === 'moov') {
        $metadata += $this->_parseMoovAtom($handle, $atom['end']);
      }

      fseek($handle, $atom['end']);
    }

    return $metadata;
  }

  /**
   * Parses the MP4 moov atom for duration and video-track dimensions.
   *
   * @param resource $handle
   *   The open file handle.
   * @param int $boundary
   *   The byte offset boundary.
   *
   * @return array
   *   Parsed metadata.
   */
  private function _parseMoovAtom($handle, int $boundary): array {
    $metadata = [];

    while (($position = ftell($handle)) !== FALSE && $position < $boundary) {
      $atom = $this->_readAtomHeader($handle, $boundary);
      if ($atom === NULL) {
        break;
      }

      if ($atom['type'] === 'mvhd' && empty($metadata['duration_seconds'])) {
        $duration = $this->_parseMvhdAtom($handle, $atom);
        if ($duration !== NULL) {
          $metadata['duration_seconds'] = $duration;
        }
      }

      if ($atom['type'] === 'trak'
            && (empty($metadata['width']) || empty($metadata['height']))
        ) {
        $track = $this->_parseTrackAtom($handle, $atom['end']);
        if (!empty($track['is_video'])) {
          $metadata['width'] = $track['width'] ?? NULL;
          $metadata['height'] = $track['height'] ?? NULL;
        }
      }

      fseek($handle, $atom['end']);
    }

    return array_filter(
              $metadata,
              static function ($value) {
                          return $value !== NULL;
              }
          );
  }

  /**
   * Parses a track atom and returns width and height for the video track.
   *
   * @param resource $handle
   *   The open file handle.
   * @param int $boundary
   *   The byte offset boundary.
   *
   * @return array
   *   Track data.
   */
  private function _parseTrackAtom($handle, int $boundary): array {
    $track = [
      'is_video' => FALSE,
      'width' => NULL,
      'height' => NULL,
    ];

    while (($position = ftell($handle)) !== FALSE && $position < $boundary) {
      $atom = $this->_readAtomHeader($handle, $boundary);
      if ($atom === NULL) {
        break;
      }

      if ($atom['type'] === 'tkhd') {
        $dimensions = $this->_parseTkhdAtom($handle, $atom);
        $track['width'] = $dimensions['width'];
        $track['height'] = $dimensions['height'];
      }

      if ($atom['type'] === 'mdia') {
        $track['is_video'] = $this->_parseMdiaAtom(
                  $handle,
                  $atom['end']
              );
      }

      fseek($handle, $atom['end']);
    }

    return $track;
  }

  /**
   * Parses an mdia atom and detects whether the track is a video track.
   *
   * @param resource $handle
   *   The open file handle.
   * @param int $boundary
   *   The byte offset boundary.
   *
   * @return bool
   *   True when the track handler is video.
   */
  private function _parseMdiaAtom($handle, int $boundary): bool {
    while (($position = ftell($handle)) !== FALSE && $position < $boundary) {
      $atom = $this->_readAtomHeader($handle, $boundary);
      if ($atom === NULL) {
        break;
      }

      if ($atom['type'] === 'hdlr') {
        $handler_type = $this->_parseHdlrAtom($handle, $atom);
        if ($handler_type === 'vide') {
          return TRUE;
        }
      }

      fseek($handle, $atom['end']);
    }

    return FALSE;
  }

  /**
   * Reads a single MP4 atom header.
   *
   * @param resource $handle
   *   The open file handle.
   * @param int $boundary
   *   The byte offset boundary.
   *
   * @return array|null
   *   Parsed atom header data, or null on failure.
   */
  private function _readAtomHeader($handle, int $boundary): ?array {
    $start = ftell($handle);
    if ($start === FALSE || ($boundary - $start) < 8) {
      return NULL;
    }

    $header = fread($handle, 8);
    if ($header === FALSE || strlen($header) < 8) {
      return NULL;
    }

    $size = $this->_readUint32(substr($header, 0, 4));
    $type = substr($header, 4, 4);
    $header_size = 8;

    if ($size === 1) {
      $extended_size = fread($handle, 8);
      if ($extended_size === FALSE || strlen($extended_size) < 8) {
        return NULL;
      }
      $size = $this->_readUint64($extended_size);
      $header_size = 16;
    }
    elseif ($size === 0) {
      $size = $boundary - $start;
    }

    if ($size < $header_size) {
      return NULL;
    }

    $end = min((int) ($start + $size), $boundary);
    if ($end <= $start) {
      return NULL;
    }

    return [
      'type' => $type,
      'start' => $start,
      'body_start' => $start + $header_size,
      'end' => $end,
    ];
  }

  /**
   * Parses the movie header atom for duration.
   *
   * @param resource $handle
   *   The open file handle.
   * @param array $atom
   *   Atom metadata.
   *
   * @return float|null
   *   The duration in seconds, or null.
   */
  private function _parseMvhdAtom($handle, array $atom): ?float {
    fseek($handle, $atom['body_start']);
    $version = fread($handle, 1);
    if ($version === FALSE || $version === '') {
      return NULL;
    }

    $version = ord($version);
    $timescale_offset = $version === 1 ? 20 : 12;
    $duration_offset = $version === 1 ? 24 : 16;
    $duration_bytes = $version === 1 ? 8 : 4;

    fseek($handle, $atom['body_start'] + $timescale_offset);
    $timescale = fread($handle, 4);
    if ($timescale === FALSE || strlen($timescale) < 4) {
      return NULL;
    }

    fseek($handle, $atom['body_start'] + $duration_offset);
    $duration = fread($handle, $duration_bytes);
    if ($duration === FALSE || strlen($duration) < $duration_bytes) {
      return NULL;
    }

    $timescale_value = $this->_readUint32($timescale);
    if ($timescale_value === 0) {
      return NULL;
    }

    if ($duration_bytes === 8) {
      $duration_value = $this->_readUint64($duration);
    }
    else {
      $duration_value = $this->_readUint32($duration);
    }

    return $duration_value / $timescale_value;
  }

  /**
   * Parses the track header atom for width and height.
   *
   * @param resource $handle
   *   The open file handle.
   * @param array $atom
   *   Atom metadata.
   *
   * @return array
   *   Width and height values.
   */
  private function _parseTkhdAtom($handle, array $atom): array {
    fseek($handle, $atom['end'] - 8);
    $raw = fread($handle, 8);
    if ($raw === FALSE || strlen($raw) < 8) {
      return ['width' => NULL, 'height' => NULL];
    }

    return [
      'width' => $this->_readFixedPoint1616(substr($raw, 0, 4)),
      'height' => $this->_readFixedPoint1616(substr($raw, 4, 4)),
    ];
  }

  /**
   * Parses the handler atom for the track type.
   *
   * @param resource $handle
   *   The open file handle.
   * @param array $atom
   *   Atom metadata.
   *
   * @return string|null
   *   The handler type, or null.
   */
  private function _parseHdlrAtom($handle, array $atom): ?string {
    fseek($handle, $atom['body_start'] + 8);
    $handler_type = fread($handle, 4);
    if ($handler_type === FALSE || strlen($handler_type) !== 4) {
      return NULL;
    }

    return $handler_type;
  }

  /**
   * Reads a big-endian unsigned 32-bit integer.
   *
   * @param string $bytes
   *   The raw bytes.
   *
   * @return int
   *   The integer value.
   */
  private function _readUint32(string $bytes): int {
    return unpack('N', $bytes)[1];
  }

  /**
   * Reads a big-endian unsigned 64-bit integer.
   *
   * @param string $bytes
   *   The raw bytes.
   *
   * @return float
   *   The integer value as a float.
   */
  private function _readUint64(string $bytes): float {
    $parts = unpack('Nhigh/Nlow', $bytes);

    return ((float) $parts['high'] * 4294967296.0)
                    + (float) $parts['low'];
  }

  /**
   * Reads a 16.16 fixed-point number.
   *
   * @param string $bytes
   *   The raw bytes.
   *
   * @return int
   *   The rounded integer value.
   */
  private function _readFixedPoint1616(string $bytes): int {
    return (int) round($this->_readUint32($bytes) / 65536);
  }

  /**
   * Formats seconds as a compact human-readable duration.
   *
   * @param float $seconds
   *   The duration in seconds.
   *
   * @return string
   *   A compact duration string.
   */
  private function _formatDuration(float $seconds): string {
    $total_seconds = (int) round($seconds);
    $hours = intdiv($total_seconds, 3600);
    $minutes = intdiv($total_seconds % 3600, 60);
    $remaining_seconds = $total_seconds % 60;

    if ($hours > 0) {
      return sprintf(
                '%d:%02d:%02d',
                $hours,
                $minutes,
                $remaining_seconds
            );
    }

    return sprintf('%d:%02d', $minutes, $remaining_seconds);
  }

}
