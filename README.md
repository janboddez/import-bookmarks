# Import Bookmarks
Import browser bookmarks into WordPress.

Export bookmarks from your browser and import them as WordPress posts. Supports Custom Post Types.

## Custom Post Types
Bookmarks can be added as regular Posts or instances of a Custom Post Type (created through, e.g., [Custom Post UI](https://wordpress.org/plugins/custom-post-type-ui/), or another plugin).

## Statuses
Newly added bookmarks can be marked Published, Draft, Pending review or Private.

## Modifying the Post Content Format of Imported Bookmarks
Some examples of what's possible. These would typically go into your (child) theme's `functions.php`. Note: core hooks like `publish_{$post->post_type}` allow even more customization, but that's outside the scope of this readme.

```
// Force open links in new tab.
add_filter( 'import_bookmarks_post_content', function( $post_content, $bookmark, $post_type ) {
    $post_title    = sanitize_text_field( $bookmark['title'] );    $post_content  = sanitize_text_field( $bookmark['note'] );
    $post_content .= "\n\n<a href='" . esc_url( $bookmark['uri'] ) . "' target='_blank' rel='noopener noreferrer'>" . $post_title . '</a>';
    $post_content  = trim( $post_content );

    return $post_content;
}, 10, 3 );
```

```
// Add (some!) Gutenberg support. Note: unlike Gutenberg's 'Convert to blocks',
// this'll not autodetect and apply, e.g., WordPress embeds.
add_filter( 'import_bookmarks_post_content', function( $post_content, $bookmark, $post_type ) {
    if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
        // Convert to paragraph blocks.
        $post_content = str_replace(
            "\n\n",
            "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>",
            $post_content
        );
        $post_content = "<!-- wp:paragraph -->\n<p>" . $post_content . "</p>\n<!-- /wp:paragraph -->";
    }

    return $post_content;
}, 10, 3 );
```

```
// Do something else entirely.
add_filter( 'import_bookmarks_post_content', function( $post_content, $bookmark ) {
    $post_content  = sanitize_text_field( $bookmark['title'] );
    $post_content .= "\n\n" . make_clickable( esc_url( $bookmark['uri'] ) );
    $post_content  = trim( $post_content );

    return $post_content;
}, 10, 2 );
```

## Changelog

### 0.2.3
Removed call to `set_time_limit()`. Updated documentation.

### 0.2.2
Modified the `import_bookmarks_post_content` filter to accept a 3rd argument.

### 0.2.1
Fixed error handling. Added `import_bookmarks_post_content` filter hook, to allow for custom bookmark markup.

### 0.2
Added translation functionality.

### 0.1
Initial release
