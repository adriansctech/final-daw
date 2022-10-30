<?php

namespace Drupal\movement_handler;

use \Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service description.
 */
class MovemensHandlerContent {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MovemensHandlerContent object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * createnodes function.
   *
   * @param String file
   *   The file that we have to process.
   *
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   */
  public function createNodes(string $file) {

    // Load excel file with all information.
    $spreadsheet = IOFactory::load($file);
    $sheets = $spreadsheet->getAllSheets();
    $sheet = $spreadsheet->getSheet(0);
    $sheeToArray = $sheet->toArray();

    $row = [];

    foreach ($sheeToArray as $key => $information) {

      if ( $key == 6) {
        $row['date'] = $information['0'];
        $row['category'] = $this->removeSpecialChars($information['1']);
        $row['subcategory'] = $this->removeSpecialChars($information['2']);
        $row['reason'] = $this->removeSpecialChars($information['3']);
        $row['amount'] = $information['6'];
        $row['category'] = $this->createCategorySubcategoryTaxonomy($row['category'], 'category');
        $row['subcategory']  = $this->createCategorySubcategoryTaxonomy($row['category'], 'subcategory');
        $this->createMovement($row);
      }
    }
  }


  /**
   * createMovement function.
   *
   * @param array $row
   *   The essentiel information to create new movement node.
   */
  private function createMovement(array $row) {
    if (!is_numeric($row['category'])) {
      $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $row['category'], 'vid' => 'category'])->id();
    }
    if (!is_numeric($row['subcategory'])) {
      $row['subcategory'] = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $row['subcategory'], 'vid' => 'subcategory'])->id();
      $row['subcategory'] = $row['subcategory']->id();
    }
    $date = new DrupalDateTime($row['date']);
    $node = Node::create([
      'type'=> 'movement',
      'title'=> $row['reason'],
      'field_movement_date' => $date->format('Y-m-d\TH:i:00'),
      'field_movement_category' => $row['category'],
      'field_movement_subcategory' => $row['subcategory'],
    ]);
    $node->save();
  }

  /**
   * createCategoryTaxonomy function.
   *
   * @aram String term
   *   With the information that we need to create new taxonomy element
   *   if this not exists.
   * @param String taxonomy
   *   With the vocabulary name.
   *
   * @return int
   *   With the tid of new taxonomy.
   */
  private function createCategorySubcategoryTaxonomy(String $term, String $taxonomy) {
    $tid = '';
    $termExists = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['name' => $term, 'vid' => $taxonomy]);
    if(empty($termExists)) {
      $term = Term::create([
        'name' => $term,
        'vid' => $taxonomy,
      ])->save();
      /*$tree = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(
        $taxonomy, 0,1,TRUE);
      $nid = sizeof($tree);*/
    }
    else {
      $tid = $termExists[array_key_first($termExists)]->id();
    }

    return $tid;
  }

  /**
   * removeSpecialChars function.
   *
   * @param String $value
   *   with values to clear.
   *
   * @return string
   *   with values cleared.
   */
  private function removeSpecialChars(String $value) {
    $value = utf8_decode($value);
    $notAlloeed = ["á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","À","Ã","Ì","Ò","Ù","Ã™","Ã ","Ã¨","Ã¬","Ã²","Ã¹","ç","Ç","Ã¢","ê","Ã®","Ã´","Ã»","Ã‚","ÃŠ","ÃŽ","Ã”","Ã›","ü","Ã¶","Ã–","Ã¯","Ã¤","«","Ò","Ã","Ã„","Ã‹"];
    $allowed = ["a","e","i","o","u","A","E","I","O","U","n","N","A","E","I","O","U","a","e","i","o","u","c","C","a","e","i","o","u","A","E","I","O","U","u","o","O","i","a","e","U","I","A","E"];
    $textCleared = str_replace($notAlloeed, $allowed ,$value);
    $textCleared = utf8_encode($textCleared);
    return $textCleared;
  }

}
