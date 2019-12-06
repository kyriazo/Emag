<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\PreviewFallbackInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Access\LayoutPreviewAccessAllowed;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\views\Plugin\Block\ViewsBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds render arrays and handles access for all block components.
 *
 * @internal
 *   Tagged services are internal.
 */
class BlockComponentRenderArray implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The core renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a BlockComponentRenderArray object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The core renderer service.
   */
  public function __construct(AccountInterface $current_user, RendererInterface $renderer) {
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 100];
    return $events;
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;
    }

    // Set block access dependency even if we are not checking access on
    // this level. The block itself may render another
    // RefinableDependentAccessInterface object and need to pass on this value.
    if ($block instanceof RefinableDependentAccessInterface) {
      $contexts = $event->getContexts();
      if (isset($contexts['layout_builder.entity'])) {
        if ($entity = $contexts['layout_builder.entity']->getContextValue()) {
          if ($event->inPreview()) {
            // If previewing in Layout Builder allow access.
            $block->setAccessDependency(new LayoutPreviewAccessAllowed());
          }
          else {
            $block->setAccessDependency($entity);
          }
        }
      }
    }

    // Only check access if the component is not being previewed.
    if ($event->inPreview()) {
      $access = AccessResult::allowed()->setCacheMaxAge(0);
    }
    else {
      $access = $block->access($this->currentUser, TRUE);
    }

    $event->addCacheableDependency($access);
    if ($access->isAllowed()) {
      $event->addCacheableDependency($block);

      // @todo Revisit after https://www.drupal.org/node/3027653, as this will
      //   provide a better way to remove contextual links from Views blocks.
      //   Currently, doing this requires setting
      //   \Drupal\views\ViewExecutable::$showAdminLinks() to false before the
      //   Views block is built.
      if ($block instanceof ViewsBlock && $event->inPreview()) {
        $block->getViewExecutable()->setShowAdminLinks(FALSE);
      }

      $content = $block->build();
      $is_content_empty = Element::isEmpty($content);
      $is_placeholder_ready = $event->inPreview() && $block instanceof PreviewFallbackInterface;
      // If the content is empty and no placeholder is available, return.
      if ($is_content_empty && !$is_placeholder_ready) {
        return;
      }
      if ($event->inPreview()) {
        $this->convertFormsToDiv($content);
      }

      $build = [
        // @todo Move this to BlockBase in https://www.drupal.org/node/2931040.
        '#theme' => 'block',
        '#configuration' => $block->getConfiguration(),
        '#plugin_id' => $block->getPluginId(),
        '#base_plugin_id' => $block->getBaseId(),
        '#derivative_plugin_id' => $block->getDerivativeId(),
        '#weight' => $event->getComponent()->getWeight(),
        'content' => $content,
      ];

      if ($event->inPreview()) {
        if ($block instanceof PreviewFallbackInterface) {
          $preview_fallback_string = $block->getPreviewFallbackString();
        }
        else {
          $preview_fallback_string = $this->t('"@block" block', ['@block' => $block->label()]);
        }
        // @todo Use new label methods so
        //   data-layout-content-preview-placeholder-label doesn't have to use
        //   preview fallback in https://www.drupal.org/node/2025649.
        $build['#attributes']['data-layout-content-preview-placeholder-label'] = $preview_fallback_string;

        if ($is_content_empty && $is_placeholder_ready) {
          $build['content']['#markup'] = $this->t('Placeholder for the @preview_fallback', ['@preview_fallback' => $block->getPreviewFallbackString()]);
        }
      }

      $event->setBuild($build);
    }
  }

  /**
   * Prevent nested forms from generating form tokens.
   *
   * @param array $content
   *   The block render array.
   *
   * @return array
   *   A render array where nested forms will not generate tokens due to
   *   #theme_wrappers being removed.
   */
  protected function detokenizeNestedForms(array $content) {
    if (is_array($content)) {
      if (isset($content['#type']) && $content['#type'] === 'form') {
        unset($content['#theme_wrappers']);
      }
      foreach ($content as $key => $child_content) {
        if (is_array($child_content)) {
          $content[$key] = $this->detokenizeNestedForms($child_content);
        }

      }
    }
    return $content;
  }

  /**
   * Convert form tags to div when displayed in the Layout Builder UI form.
   *
   * @param array $content
   *   The render array of the block.
   */
  protected function convertFormsToDiv(array &$content) {
    $markup = $this->renderer->render($content);
    $html = Html::load((string) $markup);

    // This step is only necessary if forms are present in the markup.
    if ($html->getElementsByTagName('form')->length > 0) {
      $new_markup = preg_replace('/<form(.*?)<\/form>/s', '<div$1</div>', $markup);
      $content['#markup'] = $new_markup;
    }
  }

}
