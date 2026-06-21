<?php

/**
 * @file
 */

/**
 *
 */
function orbit_media_views_data() {
  $data['media']['media_image_info'] = [
    'title' => t('Asset details'),
    'field' => [
      'title' => t('Asset details'),
      'help'  => t('Shows useful metadata for media images and locally hosted videos.'),
      'id'    => 'media_image_info',
    ],
  ];

  return $data;
}
