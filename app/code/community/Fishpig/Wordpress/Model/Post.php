<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
 
class Fishpig_Wordpress_Model_Post extends Fishpig_Wordpress_Model_Abstract
{
	/**
	 * Entity meta infromation
	 *
	 * @var string
	 */
	protected $_metaTable = 'wordpress/post_meta';	
	protected $_metaTableObjectField = 'post_id';
	
	/**
	 * Event data
	 *
	 * @var string
	*/
	protected $_eventPrefix = 'wordpress_post';
	protected $_eventObject = 'post';
	
	/**
	 * Set the model's resource
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('wordpress/post');
	}

	/**
	 * Set the categories after loading
	 *
	 * @return $this
	 */
	protected function _afterLoad()
	{
		parent::_afterLoad();

		$this->getResource()->preparePosts(array($this));
		
		return $this;
	}

	/**
	 * Returns the permalink used to access this post
	 *
	 * @return string
	 */
	public function getPermalink()
	{
		return $this->getUrl();
	}

	public function getPostFormat()
	{
		if (!$this->hasPostFormat()) {
			$this->setPostFormat(false);
			
			$formats = Mage::getResourceModel('wordpress/term_collection')
				->addTaxonomyFilter('post_format')
				->setPageSize(1)
				->load();
			
			if (count($formats) > 0) {
				$this->setPostFormat(
					str_replace('post-format-', '', $formats->getFirstItem()->getSlug())
				);
			}
		}
		
		return $this->_getData('post_format');
	}

	/**
	 * Retrieve the post GUID
	 *
	 * @return string
	 */	
	public function getGuid()
	{
		if ($this->getPostType() === 'page') {
			return Mage::helper('wordpress')->getUrl() . '?page_id=' . $this->getId();
		}
		else if ($this->getPostType() === 'post') {
			return Mage::helper('wordpress')->getUrl() . '?p=' . $this->getId();
		}
		
		return Mage::helper('wordpress')->getUrl() . '?p=' . $this->getId() . '&post_type=' . $this->getPostType();
	}

	/**
	 * Retrieve the post excerpt
	 * If no excerpt, try to shorten the post_content field
	 *
	 * @return string
	 */
	public function getPostExcerpt($maxWords = 0)
	{
		if (!$this->getData('post_excerpt')) {
			if ($this->hasMoreTag()) {
				$this->setPostExcerpt($this->_getPostTeaser(true));
			}
			else if ((int)$maxWords > 1) {
				$excerpt = explode(' ', trim(strip_tags($this->_getData('post_content'))));

				if (count($excerpt) > $maxWords) {
					$excerpt = rtrim(implode(' ', array_slice($excerpt, 0, $maxWords)), "!@£$%^&*()_-+=[{]};:'\",<.>/? ") . '...';
				}
				else {
					$excerpt = implode(' ', $excerpt);
				}
				
				$this->setPostExcerpt($excerpt);
			}
			else {
				$this->setPostExcerpt($this->getPostContent('excerpt'));
			}
		}			

		return $this->getData('post_excerpt');
	}
	
	/**
	 * Determine twhether the post has a more tag in it's content field
	 *
	 * @return bool
	 */
	public function hasMoreTag()
	{
		return strpos($this->getData('post_content'), '<!--more') !== false;
	}
	
	/**
	 * Retrieve the post teaser
	 * This is the data from the post_content field upto to the MORE_TAG
	 *
	 * @return string
	 */
	protected function _getPostTeaser($includeSuffix = true)
	{
		if ($this->hasMoreTag()) {
			$content = $this->getPostContent('excerpt');

			if (preg_match('/<!--more (.*)-->/', $content, $matches)) {
				$anchor = $matches[1];
				$split = $matches[0];
			}
			else {
				$split = '<!--more-->';
				$anchor = $this->_getTeaserAnchor();
			}
			
			$excerpt = trim(substr($content, 0, strpos($content, $split)));

			if ($excerpt !== '' && $includeSuffix && $anchor) {
				$excerpt .= sprintf(' <a href="%s" class="read-more">%s</a>', $this->getPermalink(), $anchor);
			}
			
			return $excerpt;
		}
		
		return null;
	}

	public function getParentTerm($taxonomy)
	{
		$terms = $this->getTermCollection($taxonomy)
			->setPageSize(1)
			->setCurPage(1)
			->load();
		
		return count($terms) > 0 ? $terms->getFirstItem() : false;
	}
	
	/**
	 * Get a collection of terms by the taxonomy
	 *
	 * @param string $taxonomy
	 * @return Fishpig_Wordpress_Model_Resource_Term_Collection
	 */
	public function getTermCollection($taxonomy)
	{
		return Mage::getResourceModel('wordpress/term_collection')
			->addTaxonomyFilter($taxonomy)
			->addPostIdFilter($this->getId());
	}	

	/**
	 * Retrieve a collection of all parent categories
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Category_Collection
	 */
	public function getParentCategories()
	{
		return $this->getTermCollection('category');
		return Mage::getResourceModel('wordpress/term_collection')
			->addTaxonomyFilter('post_category')
			->addFieldToFilter('main_table.term_id', array('in' => $this->getCategoryIds()));
	}

	/**
	 * Gets a collection of post tags
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Tag_Collection
	 */
	public function getTags()
	{
		return $this->getTermCollection('post_tag');
	}

	/**
	 * Retrieve the read more anchor text
	 *
	 * @return string|false
	 */
	protected function _getTeaserAnchor()
	{
		// Allows translation
		return stripslashes(Mage::helper('wordpress')->__('Continue reading <span class=\"meta-nav\">&rarr;</span>'));
	}
	
	/**
	 * Retrieve the previous post
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	public function getPreviousPost()
	{
		if (!$this->hasPreviousPost()) {
			$this->setPreviousPost(false);
			
			$collection = Mage::getResourceModel('wordpress/post_collection')
				->addIsViewableFilter()
				->addPostTypeFilter($this->getPostType())
				->addPostDateFilter(array('lt' => $this->_getData('post_date')))
				->setPageSize(1)
				->setCurPage(1)
				->setOrderByPostDate()
				->load();

			if ($collection->count() > 0) {
				$this->setPreviousPost($collection->getFirstItem());
			}
		}
		
		return $this->_getData('previous_post');
	}
	
	/**
	 * Retrieve the next post
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	public function getNextPost()
	{
		if (!$this->hasNextPost()) {
			$this->setNextPost(false);
			
			$collection = Mage::getResourceModel('wordpress/post_collection')
				->addIsViewableFilter()
				->addPostTypeFilter($this->getPostType())
				->addPostDateFilter(array('gt' => $this->_getData('post_date')))
				->setPageSize(1)
				->setCurPage(1)
				->setOrderByPostDate('asc')
				->load();

			if ($collection->count() > 0) {
				$this->setNextPost($collection->getFirstItem());
			}
		}
		
		return $this->_getData('next_post');
	}

	public function isType($type)
	{
		return $this->getPostType() === $type;
	}
	
	public function getTypeInstance()
	{
		if (!$this->hasTypeInstance() && $this->getPostType()) {
			if ($this->getPostType() === 'revision') {
				if ($this->getParentPost()) {
					$this->setTypeInstance(
						$this->getParentPost()->getTypeInstance()
					);
				}
			}
			else if ($typeInstance = Mage::helper('wordpress/app')->getPostType($this->getPostType())) {
				$this->setTypeInstance($typeInstance);
			}
			else {
				$this->setTypeInstance(Mage::helper('wordpress/app')->getPostType('post'));
			}
		}
		
		return $this->_getData('type_instance');
	}

	/**
	 * Inject string 'Protected: ' on password protected posts
	 *
	 * @return string
	 */
	public function getPostTitle()
	{
		if ($this->getPostPassword() !== '') {
			return Mage::helper('wordpress')->__('Protected: %s', $this->_getData('post_title'));
		}
	
		return $this->_getData('post_title');
	}
	
	/**
	 * Retrieve the URL for the comments feed
	 *
	 * @return string
	 */
	public function getCommentFeedUrl()
	{
		return rtrim($this->getPermalink(), '/') . '/feed/';
	}
	 
	/**
	 * Gets the post content
	 *
	 * @return string
	 */
	public function getPostContent($context = 'full')
	{
		$key = rtrim('filtered_post_content_' . $context, '_');
		
		if (!$this->hasData($key)) {
			$this->setData($key, Mage::helper('wordpress/filter')->applyFilters($this->_getData('post_content'), $this, $context));
		}
		
		return $this->_getData($key);
	}

	/**
	 * Returns a collection of comments for this post
	 *
	 * @return Fishpig_Wordpress_Model_Mysql4_Post_Comment_Collection
	 */
	public function getComments()
	{
		if (!$this->hasData('comments')) {
			$this->setData('comments', $this->getResource()->getPostComments($this));
		}
		
		return $this->getData('comments');
	}

	/**
	 * Returns a collection of images for this post
	 * 
	 * @return Fishpig_Wordpress_Model_Mysql4_Image_Collection
	 *
	 * NB. This function has not been thoroughly tested
	 *        Please report any bugs
	 */
	public function getImages()
	{
		if (!$this->hasData('images')) {
			$this->setImages(Mage::getResourceModel('wordpress/image_collection')->setParent($this->getData('ID')));
		}
		
		return $this->getData('images');
	}

	/**
	 * Returns the featured image for the post
	 *
	 * This image must be uploaded and assigned in the WP Admin
	 *
	 * @return Fishpig_Wordpress_Model_Image
	 */
	public function getFeaturedImage()
	{
		if (!$this->hasData('featured_image')) {
			$this->setFeaturedImage($this->getResource()->getFeaturedImage($this));
		}
	
		return $this->getData('featured_image');	
	}
	
	/**
	 * Get the model for the author of this post
	 *
	 * @return Fishpig_Wordpress_Model_Author
	 */
	public function getAuthor()
	{
		return Mage::getModel('wordpress/user')->load($this->getAuthorId());	
	}
	
	/**
	 * Returns the author ID of the current post
	 *
	 * @return int
	 */
	public function getAuthorId()
	{
		return $this->getData('post_author');
	}
	
	/**
	 * Returns the post date formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostDate($format = null)
	{
		if (($date = $this->getData('post_date_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}
	
	/**
	 * Returns the post date formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostModifiedDate($format = null)
	{
		if (($date = $this->getData('post_modified_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}
	
	/**
	 * Returns the post time formatted
	 * If not format is supplied, the format specified in your Magento config will be used
	 *
	 * @return string
	 */
	public function getPostTime($format = null)
	{
		if (($date = $this->getData('post_date_gmt')) === '0000-00-00 00:00:00' || $date === '') {
			$date = now();
		}
		
		return Mage::helper('wordpress')->formatDate($date, $format);
	}

	/**
	 * Determine whether the post has been published
	 *
	 * @return bool
	 */
	public function isPublished()
	{
		return $this->getPostStatus() == 'publish';
	}

	/**
	 * Determine whether the post has been published
	 *
	 * @return bool
	 */
	public function isPending()
	{
		return $this->getPostStatus() == 'pending';
	}

	/**
	 * Retrieve the preview URL
	 *
	 * @return string
	 */
	public function getPreviewUrl()
	{
		if ($this->isPending()) {
			return Mage::helper('wordpress')->getUrl('?p=' . $this->getId() . '&preview=1');
		}
		
		return '';
	}
	
	/**
	 * Determine whether the current user can view the post/page
	 * If visibility is protected and user has supplied wrong password, return false
	 *
	 * @return bool
	 */
	public function isViewableForVisitor()
	{
		return $this->getPostPassword() === '' 
			|| Mage::getSingleton('core/session')->getPostPassword() == $this->getPostPassword(); 
	}
	
	/**
	 * Determine whether the post is a sticky post
	 * This only works if the post collection has been loaded with addStickyPostsToCollection
	 *
	 * @return bool
	 */	
	public function isSticky()
	{
		return $this->_getData('is_sticky');
	}
	
	/**
	 * Determine whether a post object can be viewed
	 *
	 * @return string
	 */
	public function canBeViewed()
	{
		return $this->isPublished()
			|| ($this->getPostStatus() === 'private' && Mage::getSingleton('customer/session')->isLoggedIn());
	}
	
	/**
	 * Wrapper for self::getPermalink()
	 *
	 * @return string
	 */
	public function getUrl()
	{
		if (!$this->hasUrl()) {
			$this->setUrl($this->getGuid());
			
			if ($this->hasPermalink()) {
				$this->setUrl(Mage::helper('wordpress')->getUrl(
					$this->_urlEncode($this->_getData('permalink'))
				));
			}
			else if ($this->getTypeInstance()->isHierarchical()) {
				if ($uris = $this->getTypeInstance()->getAllRoutes()) {
					if (isset($uris[$this->getId()])) {
						$this->setUrl(Mage::helper('wordpress')->getUrl($uris[$this->getId()] . '/'));
					}
				}
			}
		}
		
		return $this->_getData('url');
	}

	/**
	 * Encode the URL, ignoring '/' character
	 *
	 * @param string $url
	 * @return string
	 */
	protected function _urlEncode($url)
	{
		if (strpos($url, '/') !== false) {
			$parts = explode('/', $url);

			foreach($parts as $key => $value) {
				$parts[$key] = urlencode($value);
			}
			
			return implode('/', $parts);
		}
		
		return urlencode($url);
	}
	
	/**
	 * Get the parent ID of the post
	 *
	 * @return int
	 */
	public function getParentId()
	{
		return (int)$this->_getData('post_parent');
	}
		
	/**
	 * Retrieve the parent page
	 *
	 * @return false|Fishpig_Wordpress_Model_Post
	 */
	public function getParentPost()
	{
		if (!$this->hasParentPost()) {
			$this->setParentPost(false);

			if ($this->getParentId()) {
				$parent = Mage::getModel('wordpress/post')
					->setPostType($this->getPostType() === 'revision' ? '*' : $this->getPostType())
					->load($this->getParentId());
				
				if ($parent->getId()) {
					$this->setParentPost($parent);
				}
			}
		}
		
		return $this->_getData('parent_post');
	}
	
	/**
	 * Retrieve the page's children pages
	 *
	 * @return Fishpig_Wordpress_Model_Mysql_Page_Collection
	 */
	public function getChildrenPosts()
	{
		return $this->getCollection()
			->addPostParentIdFilter($this->getId());
	}
	
	/**
	  * Determine whether children exist
	  *
	  * @return bool
	  */
	public function hasChildrenPosts()
	{
		return $this->getResource()->hasChildrenPosts($this);
	}
		
	/**
	 * The methods here are legacy methods that have been ported over from the old Page class
	 * These are deprecated and will be removed shortly.
	 */

	public function getMenuLabel()
	{
		return $this->getPostTitle();
	}
	
	public function getParentPage()
	{
		return $this->isType('page')
			? $this->getParentPost()
			: false;
	}	
	
	public function hasChildren()
	{
		return $this->hasChildrenPosts();
	}
	
	public function getChildren()
	{
		return $this->getChildrenPosts();
	}
	
	public function isHomepagePage()
	{
		return $this->isType('page') && (int)$this->getId() === (int)Mage::helper('wordpress/router')->getHomepagePageId();
	}
	
	public function isBlogListingPage()
	{
		return $this->isType('page') && (int)$this->getId() === (int)Mage::helper('wordpress/router')->getBlogPageId();
	}
}
