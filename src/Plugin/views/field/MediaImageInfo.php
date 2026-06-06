<?php

namespace Drupal\orbit_media\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\StringTranslation\ByteSizeMarkup;

/**
 * @ViewsField("media_image_info")
 */
class MediaImageInfo extends FieldPluginBase
{

    public function query()
    {
        $this->ensureMyTable();

        $this->ensureMyTable();
        $this->field_alias = $this->query->addField($this->tableAlias, 'mid');
    }

    public function render(ResultRow $values)
    {
          \Drupal::logger('my_module')->notice('render called for media_image_info');

        $media = $values->_entity;

        if (!$media) {
            return $this->t('No entity');
        }

        if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
            $file_id = $media->get('field_media_image')->target_id;
            $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);

            if ($file) {
                $size   = ByteSizeMarkup::create($file->getSize());
                $item   = $media->get('field_media_image')->first();
                $width  = $item->width;
                $height = $item->height;

                return $this->t(
                    '@size <br/> @w x @h px', [
                    '@size' => $size,
                    '@w'    => $width,
                    '@h'    => $height,
                    ]
                );
            }
        }

        return $this->t('N/A');
    }
}
