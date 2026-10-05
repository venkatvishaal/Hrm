<?php
namespace App\Core;

class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../../templates/' . $template . '.php';
        $html = ob_get_clean();
        if (function_exists('csrf_field')) {
            $html = preg_replace_callback('/<form\b([^>]*)>/i', static function (array $matches): string {
                $attributes = $matches[1];
                if (
                    !preg_match('/\bmethod\s*=\s*([\'"]?)post\1/i', $attributes)
                    || stripos($attributes, 'name="_csrf"') !== false
                    || stripos($attributes, "name='_csrf'") !== false
                ) {
                    return $matches[0];
                }
                return '<form' . $attributes . '>' . csrf_field();
            }, $html) ?? $html;
        }
        echo $html;
    }
}
