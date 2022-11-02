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

      if ($key >= 6) {
        // First of all check if this taxonomy exists.
        $row['category'] = $information['1'];
        $row['subcategory'] = $information['2'];
        $this->createCategorySubcategoryTaxonomy($row['category'], 'category');
        $this->createCategorySubcategoryTaxonomy($row['subcategory'], 'subcategory');
      }
    }

    foreach ($sheeToArray as $key => $information) {
      if ($key >= 6) {
        $categoryTerm = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties([
            'name' => $information['1'],
            'vid' => 'category'
          ]);
        $subcategoryTerm = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties([
            'name' => $information['2'],
            'vid' => 'subcategory'
          ]);
        $row['date'] = $information['0'];
        $row['category'] = $information['1'];
        $row['subcategory'] = $information['2'];
        $row['reason'] = $this->removeSpecialChars($information['3']);
        $row['reason'] = $information['3'];
        $row['category'] = $categoryTerm[array_key_first($categoryTerm)]->id();
        $row['subcategory'] = $subcategoryTerm[array_key_first($subcategoryTerm)]->id();
        if (!str_contains($information['1'], 'prestaciones')) {
          $row['amount'] = substr($information['6'], 1);
          $this->createMovement($row);
        }
        else {
          $row['amount'] = (int) str_replace(",", "", $information['6']);
          $this->createPayroll($row);
        }
      }
    }
  }

  /**
   * createMovement function.
   *
   * @param array $row
   *   The essential information to create new movement node.
   */
  private function createMovement(array $row) {
    // First check if node exists.
    $data = $this->entityTypeManager
    ->getStorage('node')
    ->loadByProperties([
      'type' => 'movement',
      'field_movement_amount' => $row['amount'],
    ]);
    if (empty($data)) {
      $date = new DrupalDateTime($row['date']);
      $node = Node::create([
        'type'=> 'movement',
        'title'=> $row['reason'],
        'field_movement_date' => $date->format('Y-m-d'),
        'field_movement_category' => $row['category'],
        'field_movement_subcategory' => $row['subcategory'],
        'field_movement_amount' => $row['amount'],
      ]);
      $node->setPublished(TRUE);
      $node->save();
    }
  }

  /**
   * createPayroll function.
   *
   * @param $row
   *   The essential information to create new payroll node.
   */
  private function createPayroll ($row) {
    $data = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'payroll',
        'field_movement_amount' => $row['amount'],
      ]);
      if (empty($data)) {
        $date = new DrupalDateTime($row['date']);
        $node = Node::create([
          'type'=> 'payroll',
          'title'=> $row['reason'],
          'field_movement_date' => $date->format('Y-m-d'),
          'field_movement_amount' => $row['amount'],
        ]);
        $node->setPublished(TRUE);
        $node->save();
    }
  }


  /**
   * createCategoryTaxonomy function.
   *
   * @aram String term
   *   With the information that we need to create new taxonomy element
   *   if this not exists.
   * @param String taxonomy
   *   With the vocabulary name.
   */
  private function createCategorySubcategoryTaxonomy(String $term, String $taxonomy) {
    $termExists = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['name' => $term, 'vid' => $taxonomy]);
    if(empty($termExists)) {
      $term = Term::create([
        'name' => $term,
        'vid' => $taxonomy,
      ])->save();
    }
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
