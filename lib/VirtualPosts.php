<?php
/**
 * Virtual Posts
 *
 * A class for handling virtual posts for category and taxonomy meta data.
 *
 * Copyright(c) 2014 Schuyler W Langdon
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 */
class VirtualPosts
{
    private $postType;
    private $taxonomy;

    public function __construct($postType, $taxonomy)
    {
        $this->postType = $postType;
        $this->taxonomy = $taxonomy;
    }

    public function registerPostType()
    {
        if (post_type_exists($this->postType)) {
            return;
        }
        register_post_type($this->postType, array(
            'public'             => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive'        => false,
            'hierarchical'       => false,
        ));
        register_taxonomy($this->taxonomy, $this->postType,
            array(
                'public'                => false,
                'hierarchical'          => false,
                'show_ui'               => false,
                'show_admin_column'     => false
            )
        );
        register_taxonomy_for_object_type($this->taxonomy, $this->postType);
    }

    public function getPost($termId, $flush = false)
    {
        if (isset($this->posts[$termId]) && !$flush) {
            return $this->posts[$termId];
        }
        $post = get_posts(array('post_type' => $this->postType, 'posts_per_page' => 1,
            'tax_query' => array(
                array(
                    'taxonomy' => $this->taxonomy,
                    'field' => 'slug',
                    'terms' => $termId,
                )
            )
        ));
        return $this->posts[$termId] = empty($post) ? false : current($post);
    }

    public function setPost($termId)
    {
        if (false !== $this->getPost($termId)) {
            return $this->posts[$termId];
        }
        $result = wp_insert_post(array(
            'post_type' => $this->postType,
            'post_status' => 'publish',
            'tax_input' => array($this->taxonomy => $termId))
        );
        return 0 === $result ? false : get_post($result);
    }
}
