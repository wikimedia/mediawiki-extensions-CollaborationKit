<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationHubContentHandler
 * @group Database
 */
class CollaborationHubContentHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var CollaborationHubContentHandler
	 */
	private $handler;

	public function setUp(): void {
		parent::setUp();

		$handler = new CollaborationHubContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		self::assertTrue( $empty->isValid() );
	}

	/**
	 * @covers \CollaborationHubContentHandler::fillParserOutput()
	 */
	public function testCollaborationHubDoesntDisplayJson() {
		// Create a project namespace called 'Wikipedia'
		$this->overrideConfigValue( 'Sitename', 'Wikipedia' );

		$title = $this->getServiceContainer()->getTitleFactory()->newFromText( 'Wikipedia:Hub' );

		$content = ContentHandler::makeContent(
			'{"display_name":"Test123","introduction":"Test456","footer":"Test","image":"","colour":"lightgrey","content":[]}' . "\n",
			$title,
			'CollaborationHubContent'
		);

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();

		$parserOutput = $contentRenderer->getParserOutput( $content, $title );

		$html = $parserOutput->getText();

		$this->assertStringContainsString( 'class="mw-ck-hub-intro"', $html );
	}
}
