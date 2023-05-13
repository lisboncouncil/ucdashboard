<?php

namespace Drupal\ucdashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

use League\Csv\Writer;

/**
 * Provides route responses for the Example module.
 */
class DashboardController extends ControllerBase {
  
  const TYPE_NAME = 'ucservice';
  
  const PERMISSION_EDIT_ALL = 'edit any ucservice content';
  
  const LAST_REVISION = 1;
  
  public $schema_ind;
  
  public $revisions;
  
  public $show_percentage;
  
  public $thresholds = [];
  
  
  /**
   * Load the settings in config/install/ucdashboard.settings.yml
   */
  private function load_schema() {
    
    // Default settings.
    $config = \Drupal::config('ucdashboard.settings');
    
    $this->thresholds = [
        $config->get('ucdashboard.threshold_low'),
        $config->get('ucdashboard.threshold_medium'),
        $config->get('ucdashboard.threshold_high'),
    ];
    
    $this->show_percentage = $config->get('ucdashboard.show_percentage');
    
    $this->revisions = $config->get('ucdashboard.revisions');
    
    foreach($this->revisions as $rev) {
      $this->schema_ind[] = $rev['schema'] ?? [] ;
    }
    
  }
  

  /**
   * Returns a simple page.
   *
   * @return array
   *   A renderable array.
   */
  public function dashboard() {
    
    $this->load_schema();
    
    $revs = $dataset = $stats = [];
    
    foreach($this->revisions as $revision_id => $rev) {
      
      if(empty($rev['op'])) {
        $parsed = $this->_get_parsed_nodes(self::LAST_REVISION, $revision_id);
      }
      else{
        $parsed = $this->_get_parsed_nodes([
            'op'=> $rev['op'], 
            'date'=> $rev['date'],
          ], $revision_id);
      }
      
      self::sort_by_average($parsed);
      $dataset[] = $this->_add_rowspan($this->_add_ranking($parsed));
      
      unset($rev['schema']);
      $revs[] = $rev;
      
      // Topic stats
      $topic_stats = $this->get_topic_stats($parsed);
      $stats[] = $topic_stats->stats;
      
    }
    
    return [
      '#dataset' => $dataset,
      '#stats' => $stats,
      '#revisions' => $revs,
      '#show_percentage' => $this->show_percentage,
      '#theme' => 'ucdash_main',
      '#cache' => [
        'max-age' => 0,
      ]
    ];
  }
  
  private function _add_ranking($dataset) {
      
      $n=1;
      
      for($i=0;$i<count($dataset);$i++) {

        $actual = round($dataset[$i]->average * 100);
        $prev = (isset($dataset[$i-1]->average)) ? round($dataset[$i-1]->average * 100) : 0;

        if($actual != $prev) {
            $n = $i+1;
        }

        $dataset[$i]->rank = $n;
      }
      
      return $dataset;
  }
  
  private function _add_rowspan($dataset) {
      
      for($i=0;$i<count($dataset);$i++) {
          
          $actual = round($dataset[$i]->average * 100);
          
          $rowspan = 1;
          
          for($j=$i+1; $j<count($dataset); $j++) {
              
              if(round($dataset[$j]->average * 100) == $actual) {
                  $rowspan++;
              }
          }
          
          $dataset[$i]->rowspan = $rowspan;
          
      }
      
      return $dataset;
  }
  
  
  public function thankyou() {
    
    return [
      '#title' => 'Benchmarking Dashboard',
      '#theme' => 'ucdash_thankyou',
    ];
  }
  
  public function get_topic_stats($parsed) {
    
    $topics= (object) [
        'data'=>[],
        'stats' => [],
        'class' => [],
    ];
    
    foreach($parsed as $row) {
        
      foreach($row->sub as $sub) {
        
        $topics->data[$sub->category]['yes'][] = $sub->yes;
        $topics->data[$sub->category]['n'][] = $sub->n;
      }
    }
    
    foreach($topics->data as $cat => $topic) {
      
      static $i=1;
      
      $avg = round(array_sum($topic['yes']) / array_sum($topic['n']), 2);
      $topics->stats[$cat] = [
          $avg * 100,
          $this->_assign_class($avg, $this->thresholds),
          $i,
      ];
      
      $i++;
    }
    
    return $topics;
  }
  
  
  
  public function about() {
    
    return [
      '#title' => 'About Benchmarking Dashboard',
      '#theme' => 'ucdash_about',
    ];
  }
  
  /**
   * Return the data of the dashboard.
   *
   * @return Response
   */
  public function opendata() {
      
    $parsed = $this->_get_parsed_nodes();
    
    //load the CSV document from a string
    $csv = Writer::createFromString();
    
    $rows = [];
    
    foreach($parsed as $k=>$RS) {
        
        if($k==0) {
            $header = [];
            $header[] = 'name';
            foreach($RS->sub as $kk) {
                $header[]=$kk->key. ' - '. $kk->name;
            }
            //insert the header
            $csv->insertOne($header);
        }
        
        $row = [];
        $row[] = $RS->title;
        foreach($RS->sub as $sub) {
            $row[] = $sub->yes . '/'  .$sub->n;
        }
        $rows[] = $row;
    }
    

    //insert all the records
    $csv->insertAll($rows);

    $filename = 'uccs-dashboard.csv';
    
    $headers = [
      'Content-Type' => 'text/csv',
      'Content-Description' => 'File Download',
      'Content-Disposition' => 'attachment; filename=' . $filename
    ];

     // Return a response with a specific content
    $response = new Response($csv->toString(), 200, $headers);

    // Dispatch request
    return $response;
  }
  
  
  /**
   * Page Single record. 
   * 
   * @param int $nid
   * @return type
   */
  public function single($nid, $revision_id=0) {
    
    $this->load_schema();
    
    $node =  \Drupal\node\Entity\Node::load($nid);
    
    // $type = $node->getType();
    $title = $node->getTitle();
    
    $avgs = $this->subtopic_avg();
    $parsed = $this->parse_node($node);
    
    $data_hc = $this->_prepare_hcdata($parsed, $avgs);
    
    $can_edit = $this->_currentuser_can_edit($node);
    
    $h2 = '';
    
    $data = [];
    
    foreach($this->schema_ind[$revision_id] as $indicator) {
      
      $rows = [];
      
      foreach($indicator['ff'] as $f) {
        
        if($h2 == '' || $h2 != $indicator['category']) {
          $indicator['_cat'] = $indicator['category'];
        }
        
        $_label = '<div class="question">'
                .$node->get('field_' . $f)->getFieldDefinition()->getLabel()
                .'</div>';
        $_label.= $this->_add_evidence($f, $node);
        
        $value = $node->get('field_' . $f)->getString();
        $_value = ($value == '') ? 'NA' : $value;
        
        $rows[] = ['label' => $_label, 'value' => $_value];
        
      }
      
      $h2 = $indicator['category'];
      
      $data[] = [
          'indicator' => $indicator,
          'rows' => $rows,
      ];
      
    }
    
    
    return [
      '#data' => $data,
      '#node0' => [
          'total_number_employees' => $node->get('field_00_total_number_emp')->getString(),
          'total_population' => $node->get('field_00__total_population')->getString(),
          'total_budget' => $node->get('field_00_total_budget')->getString(),
          'how_many_employ' => $node->get('field_sk_how_many_ict_specialist')->getString(),
          'how_many_subcontractors' => $node->get('field_sk_ict_subcontractors')->getString(),
          'title' => $title,
          'nid' => $nid,
          'data_hc' => $data_hc,
          'can_edit' => intval($can_edit),
      ],
      '#theme' => 'ucdashboard_detail',
      '#attached' => [
        'library' => [
          'ucdashboard/ucdashboard',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
  
  
  
  
  /**
   * Get the avg of all nodes.
   * 
   * @param array $parsed_nodes
   * @return array
   */
  public function subtopic_avg($parsed_nodes=null) {
    
    if($parsed_nodes == null) {
      $parsed_nodes = $this->_get_parsed_nodes();
    }
    
    $data= $avgs = [];
    
    foreach($parsed_nodes as $row) {
      foreach($row->sub as $sub) {
        $data[ (string) $sub->key][] = $sub->val;
      }
    }
   
    foreach($data as $k => $row) {
      $avgs[$k] = self::_array_avg($row);
    }
    
    return $avgs;
    
  }
  
  /**
   * Returns the average of an array.
   *
   * @param array $array
   * @return double
   */
  private static function _array_avg($array) {
    
    if(is_array($array) && count($array) > 0) {
      return array_sum($array) / count($array);
    }
    else{
      return 0;
    }
    
  }
  
  /**
   * Get all the nodes and return the full data.
   * 
   * @return array
   */
  private function _get_parsed_nodes($version = self::LAST_REVISION, $revision_id = 0) {
    
    $this->load_schema();
    
    if($version === self::LAST_REVISION) {

      $nids = \Drupal::entityQuery('node')
              ->accessCheck(TRUE)
              ->condition('type',self::TYPE_NAME)
              // ->condition('status', 1)
              ->sort('title', 'ASC')
              ->execute();
      $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);

    }
    else if(is_array($version)) {
      
      $nodes = $this->_get_parsed_nodes_version($version);
    }
    
    $parsed = [];
    
    foreach($nodes as $k=>$node) {
      
       $parsed[] = $this->parse_node($node, $revision_id);
    }
    
    return $parsed;
  }
  
  
  private function _get_parsed_nodes_version($version) {
    
    $op = $version['op'] ?? '<';
    $date = $version['date'] ?? '2023-01-01';
    
    $op_clean = (in_array($op, ['<','<=','>','>=','=', '!='])) ? $op : '<';
    
    $sql = "SELECT nr.vid
      FROM {node_field_data} nd
      INNER JOIN {node_field_revision} nr ON nd.nid = nr.nid
      INNER JOIN 
        (SELECT MAX(vid) vid
        FROM {node_field_revision}
        WHERE 1=1
        AND status = 1
        AND changed $op_clean :date
        AND moderation_state = :published
        GROUP BY nid
        ) as nr2 ON nr2.vid = nr.vid  
      WHERE nd.type='ucservice'
      AND nd.status = 1 
    ";
    
    $database = \Drupal::database();
    $query = $database->query($sql, [
        ':date' => $date,
        ':published' => 'published',
    ]);
    $result = $query->fetchAll();
    
    $nodes=[];
    
    foreach($result as $row) {
      
      $vid = intval($row->vid);
      $nodes[] = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($vid);
    }

    return $nodes;
    
  }
  
  
  private function _prepare_hcdata($parsed_node, $avgs) {
    
    $subs = $parsed_node->sub;
    
    $categories = [];
    $data1 = $data_avg = [];
    
    foreach($subs as $sub) {
      $categories[] = $sub->key . " - ". $sub->name;
      
      $data1[] = $sub->val;
      $data_avg[] = $avgs["$sub->key"];
    }
    
    return (object) [
      'categories' => $categories,
      'data1' => $data1,
      'data_avg' => $data_avg,
    ];
  }
  
  
  
  private function _add_evidence($field_name, $node) {
    
    $field_evidence_name = 'field_' . $field_name . '_ev';
    
    if($node->hasField($field_evidence_name)) {
      
      $evidence = $node->get($field_evidence_name)->getString();
      
      if(empty($evidence)) {
        return '';
      }
      
      $html= '<div class="evidence"><strong>Evidence: </strong>';
      $html.= $evidence . "</div>";
      
      return $html;
    }
    else{
      return '';
    }
  }
  
  
  
  private function parse_node($node, $revision_id = 0) {
    
    $res = [];
    
    $res['title'] = $node->get('title')->getString();
    $res['nid'] = $node->get('nid')->getString();
    $res['alias'] = \Drupal::service('path_alias.manager')->getAliasByPath('/ucdashboard/'.$res['nid']);
    
    foreach($this->schema_ind[$revision_id] as $indicator) {
      
      $o = new \stdClass();
      $o->key = $indicator['key'];
      $o->name = $indicator['name'];
      $o->category = $indicator['category'];
      $o->n = count($indicator['ff']);
      $o->yes = 0;

      foreach($indicator['ff'] as $f) {

        $v = ($node->get('field_' . $f)->getString() == 'Yes') ? 1:0;
        $o->yes+= $v;
      }

      $o->val = ($o->n > 0 ) ? round($o->yes / $o->n, 2) : 0;
      $o->class = $this->_assign_class($o->val, $this->thresholds);
      $res['sub'][] = $o;
    }
    
    // Calculate the row average
    $res['average'] = $this->_row_average($res['sub']);
            
    return (object) $res;
    
  }
  
  /**
   * Calculate the row average.
   * @param array $sub
   * @return type
   */
  private function _row_average($sub) {
      
      $values = [];
      
      if(is_array($sub)) {
        foreach ($sub as $el) {
            $values[] = $el->val ;
        }
      }
      
      return (count($values) > 0) 
        ? round(array_sum($values) / count($values), 3) : null;
  }
  
  private function _assign_class($value, $thresholds) {
    
    foreach($thresholds as $k=>$th) {
      if($value <= $th) {
        return $k;
      }
    }
    
    return null;
  }
  
  
  
  private function _currentuser_can_edit($node) {
      
      $user = \Drupal::currentUser();
      $user_id = \Drupal::currentUser()->id();
      
      if($user_id == 1 || $user->hasPermission(self::PERMISSION_EDIT_ALL)) {
          
          return true;
      }
      else{
          
          $author = $node->getOwnerId();
          
          return (intval($user_id)>0 && intval($user_id) === intval($author));
      }
  }


  public static function sort_by_average(&$parsed) {
    if(is_array($parsed)) {
      usort($parsed, function($a, $b) {return strcmp($b->average, $a->average);});
    }
  }
}