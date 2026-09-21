<?php
declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = "layouts/app"): void
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture($layout, $data + ["content" => $content]);
    }

    public static function capture(string $template, array $data = []): string
    {
        $file = APP_PATH . "/views/" . $template . ".php";
        if (!is_file($file)) {
            throw new RuntimeException("View not found: " . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): void
    {
        echo self::capture("partials/" . $template, $data);
    }
}
