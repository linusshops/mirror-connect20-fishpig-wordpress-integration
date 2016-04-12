<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Term_Feed extends Fishpig_Wordpress_Block_Feed_Post
{
	/**
	 * Get a collection of posts that belong to the term
	 *
	 * @return Fishpig_Wordpress_Model_Resource_Post_Collection|false
	 */
	public function getPostCollection()
	{
		if (($term = Mage::registry('wordpress_term')) !== null) {
			return $term->getPostCollection();
		}
		
		return false;
	}
}
