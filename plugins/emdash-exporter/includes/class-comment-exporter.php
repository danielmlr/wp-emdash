<?php
/**
 * Comment Exporter
 *
 * Exports approved and pending comments in the shape EmDash's
 * importCommentsFromPlugin() expects. Spam and trash are excluded --
 * nobody wants to migrate spam.
 */

defined('ABSPATH') || exit;

class EmDash_Comment_Exporter {

    /**
     * Get comments, paginated, oldest first (stable pagination and
     * parents-before-children ordering for the importer).
     *
     * @param int $per_page Items per page
     * @param int $page     Page number (1-based)
     * @return array
     */
    public function get_comments($per_page = 500, $page = 1) {
        $args = [
            'status' => ['approve', 'hold'],
            'type' => 'comment',
            'orderby' => 'comment_ID',
            'order' => 'ASC',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];

        $comments = get_comments($args);

        $total = (int) get_comments(array_merge($args, [
            'count' => true,
            'number' => 0,
            'offset' => 0,
        ]));

        $items = [];
        foreach ($comments as $comment) {
            $items[] = [
                'id' => (int) $comment->comment_ID,
                'post_id' => (int) $comment->comment_post_ID,
                'parent_id' => (int) $comment->comment_parent ?: null,
                'author_name' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                // EmDash renders comment bodies as escaped plain text
                'body' => wp_strip_all_tags($comment->comment_content),
                // comment_date_gmt is "Y-m-d H:i:s" in UTC
                'date_gmt' => str_replace(' ', 'T', $comment->comment_date_gmt) . 'Z',
                'status' => $comment->comment_approved === '1' ? 'approved' : 'pending',
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Count exportable comments (approved + pending), for the wizard overview.
     *
     * @return int
     */
    public static function count() {
        return (int) get_comments([
            'status' => ['approve', 'hold'],
            'type' => 'comment',
            'count' => true,
        ]);
    }
}
