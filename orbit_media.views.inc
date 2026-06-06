<?php

function orbit_media_views_data()
{
    $data['media']['media_image_info'] = [
    'title' => t('Image size & dimensions'),
    'field' => [
      'title' => t('Image size & dimensions'),
      'help'  => t('Shows file size and pixel dimensions for media images.'),
      'id'    => 'media_image_info',
    ],
    ];

    return $data;
}
