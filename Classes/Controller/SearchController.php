<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Ingo Pfennigstorf <pfennigstorf@sub-goettingen.de>
 *      Goettingen State Library
 *  
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

require_once(t3lib_extMgm::extPath('solr_frontend') . 'vendor/autoload.php');

/**
 * Description
 */
class Tx_SolrFrontend_Controller_SearchController extends Tx_Extbase_MVC_Controller_ActionController {

	/**
	 * @var \Solarium\Client
	 */
	protected $solr;

	/**
	 * @var Tx_SolrFrontend_Domain_Model_Search
	 * @inject
	 */
	protected $search;

	/**
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * @var int
	 */
	protected $resultsPerPage;

	/**
	 * @var string
	 */
	public $prefixId = 'tx_solrfrontend_solrfrontend';

	/**
	 * Initializes some defaults
	 */
	public function initializeAction() {
		$this->addResourcesToHead();
		
		$configuration = array(
			'endpoint' => array(
			'localhost' => array(
				'host' => $this->settings['connection']['host'],
				'port' => intval($this->settings['connection']['port']),
				'path' => $this->settings['connection']['path'],
			)
		));

		$this->solr = new Solarium\Client($configuration);

		$this->resultsPerPage = intval($this->settings['results']['numberOfResultsPerPage']);

		if ($this->request->hasArgument('offset')) {
			$this->offset = $this->request->getArgument('offset') * $this->resultsPerPage;
		}
	}

	/**
	 * @param Tx_SolrFrontend_Domain_Model_Search $search
	 */
	public function indexAction(Tx_SolrFrontend_Domain_Model_Search $search = NULL) {
		$query = $this->solr->createSelect();

		// offset for pagination
		$query->setStart($this->offset)->setRows($this->resultsPerPage);

		// determine searchterm
		if ($search) {
			$this->search = $search;
			$searchTerm = $search->getQ();
		} elseif ($this->request->hasArgument('q')) {
			$searchTerm = $this->request->getArgument('q');
		} else {
			$searchTerm = '*';
		}

		// extra parameters a.k.a filter query
		if (!empty($this->settings['extraParameters'])) {
			// define filters
			foreach($this->settings['extraParameters'] as $title => $field) {
				$query->createFilterQuery($title)
						->setQuery($title . ':' . $field);
			}
		}

		// filter based on facet selection @todo multiple facets
		if ($this->request->hasArgument('facet')) {
			$facetTitle = $this->request->getArgument('facet');
			$query->createFilterQuery($facetTitle)
					->setQuery($facetTitle . ':' . $this->request->getArgument('selected'));
		}

		$query->setQuery($searchTerm);

		// get the facetset component
		$facetSet = $query->getFacetSet();

		if (!empty($this->settings['facets'])) {
			// @todo sort array
			// define facets
			foreach($this->settings['facets'] as $title => $field) {
				$facetSet->createFacetField($field . '')->setField($field);
			}
		}
		// fire the query
		$resultSet = $this->solr->select($query);

		// determine number of pages for pagebrowser
		$numberOfPages = ceil($resultSet->getNumFound() / $this->resultsPerPage);

		$cObjectData = $this->request->getContentObjectData();

		$this->view
				->assign('results', $resultSet)
				->assign('searchTerm', $searchTerm)
				->assign('numberOfPages', $numberOfPages)
				->assign('search', $this->search)
				->assign('uid', $cObjectData['uid'])
				->assign('prefixId', $this->prefixId);
		}


	/**
	 * Creates and inserts tags inside <head>.
	 */
	protected function addResourcesToHead () {
		// Add CSS to head: Custom file if configured, included default file otherwise.
		$CSSFileName = $GLOBALS['TSFE']->tmpl->getFileName($this->settings['CSSPath']);
		if ($CSSFileName) {
			$cssTag = new Tx_Fluid_Core_ViewHelper_TagBuilder('link');
			$cssTag->addAttribute('rel', 'stylesheet');
			$cssTag->addAttribute('type', 'text/css');
			$cssTag->addAttribute('href', $CSSFileName);
			$this->response->addAdditionalHeaderData( $cssTag->render() );
		}
		
		// Add JavaScript to head: Custom file if configured, included default file otherwise.
		$scriptFileName = $GLOBALS['TSFE']->tmpl->getFileName($this->settings['JSPath']);
		if ($scriptFileName) {
			$scriptTag = new Tx_Fluid_Core_ViewHelper_TagBuilder('script');
			$scriptTag->addAttribute('type', 'text/javascript');
			$scriptTag->addAttribute('src', $scriptFileName);
			$scriptTag->forceClosingTag(true);
			$this->response->addAdditionalHeaderData( $scriptTag->render() );
		}
	}

}