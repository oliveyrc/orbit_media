<?php

declare(strict_types=1);

namespace Drupal\orbit_media\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Media controller for Orbit Media module.
 */
class MediaController extends ControllerBase
{

    /**
     * Displays the Orbit Media overview page.
     *
     * @return array
     *   A render array with the page content.
     */
public function overview(): array
    {
        $build = [];

        $build['content'] = [
        '#markup' => $this->t('<p>PNG, JPG and SVG each have different strengths, and choosing the right format can have a significant impact on image quality, accessibility and page performance. JPG (or JPEG) is generally the best choice for photographs and complex images containing lots of colours, gradients and detail. It uses compression to keep file sizes small, making it ideal for banners, hero images and editorial photography where fast page loading is important. The trade-off is that JPG compression is lossy, meaning image quality can gradually degrade if the file is repeatedly edited and saved.</p>'),
        ];

        $build['content_2'] = [
          '#markup' => $this->t('<p>PNG is best suited to graphics that require transparency, crisp edges or lossless image quality. Common examples include logos with transparent backgrounds, screenshots, diagrams and images containing text. While PNG files typically produce larger file sizes than JPGs, they preserve image quality and support full alpha transparency. SVG differs from both formats because it is vector-based rather than pixel-based. This makes it ideal for logos, icons, illustrations and simple graphics that need to scale perfectly to any size without losing quality. SVG files are often extremely small and remain sharp on high-resolution displays, but they are not suitable for photographs or highly detailed imagery.</p>')
        ];

        $build['content_3'] = [
         '#markup' => $this->t('<p>As a general rule: use JPG for photographs, PNG for transparent or detailed graphics, and SVG for logos, icons and scalable artwork.</p>')
        ];

        return $build;
    }

}
