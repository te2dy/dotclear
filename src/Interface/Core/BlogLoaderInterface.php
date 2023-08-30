<?php
/**
 * Blog loader inerface.
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Interface\Core;

use dcBlog;

interface BlogLoaderInterface
{
    /**
     * Check if a blog is set.
     *
     * @return  bool    True if a blog is loaded
     */
    public function hasBlog(): bool;

    /**
     * Get current blog.
     *
     * Returns null if no blog is currently set
     *
     * @return null|dcBlog
     */
    public function getBlog(): ?dcBlog;

    /**
     * Set the blog to use.
     *
     * @param      string  $id     The blog ID
     */
    public function setBlog(string $id): void;

    /**
     * Unset blog property.
     */
    public function unsetBlog(): void;
}
