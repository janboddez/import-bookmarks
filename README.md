# Import Bookmarks
Import browser bookmarks into WordPress.

Export bookmarks from your browser and import them as WordPress posts. Supports Custom Post Types and Post Formats.

## Installation
In WP Admin, go to *Plugins > Add New*, and search for "import bookmarks." Install and activate. Or install from https://wordpress.org/plugins/import-bookmarks/.

## Usage
In WP Admin, head over to *Import > Import Bookmarks*, select a bookmarks file (in **HTML format**, as exported from your browser of choice) to upload, and choose the destination post type and status.

**Note:** *all bookmarks* in the export file will be imported as WordPress posts. You may find you have to delete some afterward, or manually tweak the bookmarks file's content before.

### Custom Post Types
Bookmarks can be added as regular Posts or instances of a Custom Post Type (created through, e.g., [Custom Post UI](https://wordpress.org/plugins/custom-post-type-ui/), or another plugin).

This plugin, on its own, will **not** register a new Custom Post Type (nor modify existing ones).

### Post Statuses
Newly added bookmarks can be marked "Published," "Draft," "Pending review" or "Private."

### Post Formats
Newly added bookmarks can be given any [Post Format](https://developer.wordpress.org/themes/functionality/post-formats/). Note: it's your active theme that decides what this means for your site. If unsure, you'll probably want to use either "Link" or "Standard." Lastly, *only Post Types that support Post Formats are actually affected*.

### Dates and Archives
**Post dates** are set to the date the bookmark was created. Monthly archives, etc., work just fine.

**Duplicated entries** are not (yet) detected.

A **possible use case** would be to periodically "dump" all of your bookmarks into a WordPress-powered "linklog," then clear them all from your browser—rinse, repeat. (However, if you *did* want to import the same link twice, e.g., with a different title, that would be perfectly fine.)

## Advanced: Modifying the Format of Imported Bookmarks
Use the `import_bookmarks_post_content` filter to **customize imported bookmarks' markup** with minimal PHP.

Some examples of what's possible. (These would typically go into your [child] theme's `functions.php`.) Note: core hooks like `publish_{$post->post_type}` allow even more customization, but that's outside the scope of this readme.

In the future, some of these options might make it into the plugin itself.

```
// Force open links in new tab.
add_filter( 'import_bookmarks_post_content', function( $post_content, $bookmark ) {
    $post_title    = sanitize_text_field( $bookmark['title'] );
    $post_content  = sanitize_text_field( $bookmark['note'] );
    $post_content .= "\n\n<a href='" . esc_url( $bookmark['uri'] ) .
        "' target='_blank' rel='noopener noreferrer'>" . $post_title . '</a>';
    $post_content  = trim( $post_content );

    return $post_content;
}, 10, 2 );
```

```
// Add (some!) Gutenberg support. Note: unlike Gutenberg's 'Convert to blocks',
// this'll not autodetect and apply, e.g., WordPress embeds.
add_filter( 'import_bookmarks_post_content', function( $post_content, $bookmark, $post_type ) {
    if ( function_exists( 'use_block_editor_for_post_type' ) &&
         use_block_editor_for_post_type( $post_type ) ) {
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

## Credits
Icon, Copyright 2016 Nick Roach
Licensed under the terms of the GNU GPL, Version 2
Source: https://www.elegantthemes.com/blog/freebie-of-the-week/beautiful-flat-icons-for-free
