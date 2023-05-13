<?php

namespace Drupal\ucdashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'HighchartsBlock' block.
 *
 * @Block(
 *  id = "custom_highcharts",
 *  admin_label = @Translation("Highcharts"),
 * )
 */
class HighchartsBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'highcharts-block'; 
    return $build;
  }
}
