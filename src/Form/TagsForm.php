<?php

namespace Drupal\opencalais_api\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\opencalais_api\CalaisService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto tag content with the OpenCalais service.
 */
class TagsForm extends FormBase {

  /**
   * The OpenCalais service.
   *
   * @var \Drupal\opencalais_api\CalaisService
   */
  protected $calaisService;

  /**
   * Wrapper object for simple configuration from diff.settings.yml.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a RevisionOverviewForm object.
   *
   * @param \Drupal\opencalais_api\CalaisService $calais_service
   *   The OpenCalais service.
   */
  public function __construct(CalaisService $calais_service) {
    $this->config = $this->config('opencalais_api.settings');
    $this->calaisService = $calais_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opencalais_api.calais_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opencalais_api_tags';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form_state->set('entity', $node);
    $display = EntityFormDisplay::collectRenderDisplay($node, 'default');
    $type = NodeType::load($node->getType());
    foreach ($display->getComponents() as $name => $options) {
      if ($name == $type->getThirdPartySetting('opencalais_api', 'field') || $options['type'] == 'text_textarea_with_summary') {
        continue;
      }
      $display->removeComponent($name);
    }
    $form_state->set('form_display', $display);
    $form_state->get('form_display')->buildForm($node, $form, $form_state);

    // Add a submit button. Give it a class for easy JavaScript targeting.
    $form['suggested_tags'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'opencalais-suggested-tags',
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 999,
    ];
    $form['actions']['suggest_tags'] = [
      '#type' => 'submit',
      '#value' => t('Suggest Tags'),
      '#attributes' => ['class' => ['opencalais_submit']],
      '#ajax' => [
        'callback' => '::suggestTagsAjax',
        'wrapper' => 'opencalais-suggested-tags',
        'effect' => 'fade',
      ],
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * Get the form for mapping breakpoints to image styles.
   */
  public function suggestTagsAjax(array $form, FormStateInterface $form_state) {
    $result = $this->calaisService->analyze($form_state->getValue('body')[0]['value']);
    $element['social_tags'] = [
      '#type' => 'details',
      '#title' => 'Social Tags',
      '#open' => TRUE,
    ];
    $element['entities'] = [
      '#type' => 'details',
      '#title' => 'Entities',
      '#open' => TRUE,
    ];

    foreach ($result['social_tags'] as $key => $value) {
      $element['social_tags'][opencalais_make_machine_name($key)] = [
        '#type' => 'label',
        '#title' => $value,
      ];
    }
    foreach ($result['entities'] as $key => $value) {
      $element['entities'][opencalais_make_machine_name($key)] = [
        '#type' => 'label',
        '#title' => $key,
      ];
      foreach ($value as $entity_id => $entity_value) {
        $element['entities'][opencalais_make_machine_name($key)][$entity_id] = [
          '#type' => 'markup',
          '#markup' => $entity_value,
        ];
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = clone $form_state->get('entity');
    $form_state->get('form_display')->extractFormValues($entity, $form, $form_state);
    $form_state->set('entity', $entity);
  }

}
