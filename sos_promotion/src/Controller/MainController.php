<?php
namespace Drupal\sos_promotion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sos_promotion\Form\AddPromotionForm;
use Drupal\sos_promotion\Form\ManagePromotionForm;

class MainController extends ControllerBase {

    /**
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * Constructs a MainController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder.
     */
    public function __construct(FormBuilderInterface $form_builder) {
        $this->formBuilder = $form_builder;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('form_builder')
        );
    }

    /**
     * Add and Manage forms.
     *
     * @return array
     *   A render array containing both forms.
     */
    public function content() {
        return [
            'add_form' => $this->formBuilder->getForm(AddPromotionForm::class),
            'manage_form' => $this->formBuilder->getForm(ManagePromotionForm::class),
        ];
    }
}