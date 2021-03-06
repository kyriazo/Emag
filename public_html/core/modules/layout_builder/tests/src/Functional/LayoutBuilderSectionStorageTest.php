<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UI aspects of section storage.
 *
 * @group layout_builder
 */
class LayoutBuilderSectionStorageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
    'layout_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'bundle_with_section_field']);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);
  }

  /**
   * Tests that section loading is delegated to plugins during rendering.
   *
   * @see \Drupal\layout_builder_test\Plugin\SectionStorage\TestStateBasedSectionStorage
   */
  public function testRenderByContextAwarePluginDelegate() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
    ]));

    // No blocks exist on the node by default.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Defaults block title');
    $assert_session->pageTextNotContains('Test block title');

    // Enable Layout Builder.
    $this->drupalPostForm('admin/structure/types/manage/bundle_with_section_field/display/default', ['layout[enabled]' => TRUE], 'Save');

    // Add a block to the defaults.
    $page->clickLink('Manage layout');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'Defaults block title');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Defaults block title');
    $assert_session->pageTextNotContains('Test block title');

    // Enable the test section storage.
    $this->container->get('state')->set('layout_builder_test_state', TRUE);
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Defaults block title');
    $assert_session->pageTextContains('Test block title');

    // Disabling layout builder and test storage restores the original output.
    $this->drupalPostForm('admin/structure/types/manage/bundle_with_section_field/display/default', ['layout[enabled]' => FALSE], 'Save');
    $page->pressButton('Confirm');
    $assert_session->pageTextContains('Layout Builder has been disabled');
    $this->container->get('state')->set('layout_builder_test_state', FALSE);
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Defaults block title');
    $assert_session->pageTextNotContains('Test block title');
  }

}
