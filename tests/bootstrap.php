<?php

/**
 * PHPUnit bootstrap for CWM Scripture Library tests.
 *
 * Provides minimal Joomla stubs so library classes can be tested
 * without a full Joomla installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Stub _JEXEC constant
if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

// Stub Joomla\CMS\Language\Text if not available
if (!class_exists(\Joomla\CMS\Language\Text::class)) {
    // Create a minimal namespace structure
    eval('
        namespace Joomla\CMS\Language;
        class Text {
            public static function _(string $key): string {
                return $key;
            }
            public static function sprintf(string $key, ...$args): string {
                return vsprintf($key, $args);
            }
        }
    ');
}

// Stub Joomla\CMS\Log\Log if not available
if (!class_exists(\Joomla\CMS\Log\Log::class)) {
    eval('
        namespace Joomla\CMS\Log;
        class Log {
            public const ALL = 0;
            public const EMERGENCY = 1;
            public const ALERT = 2;
            public const CRITICAL = 4;
            public const ERROR = 8;
            public const WARNING = 16;
            public const NOTICE = 32;
            public const INFO = 64;
            public const DEBUG = 128;
            public static function add(string $message, int $priority = self::INFO, string $category = ""): void {}
            public static function addLogger(array $options, int $priorities = self::ALL, array $categories = []): void {}
        }
    ');
}

// Stub Joomla\CMS\Factory if not available
if (!class_exists(\Joomla\CMS\Factory::class)) {
    eval('
        namespace Joomla\CMS;
        class Factory {
            private static $container;
            public static function getContainer() {
                if (!self::$container) {
                    self::$container = new class {
                        public function get(string $id) {
                            throw new \RuntimeException("No container available in test context: " . $id);
                        }
                    };
                }
                return self::$container;
            }
            public static function getApplication() {
                return new class {
                    public function getDocument() {
                        return new class {
                            public function getWebAssetManager() {
                                return new class {
                                    public function useStyle(string $name) { return $this; }
                                    public function useScript(string $name) { return $this; }
                                };
                            }
                        };
                    }
                    public function getLanguage() {
                        return new class {
                            public function getTag(): string { return "en-GB"; }
                        };
                    }
                };
            }
            public static function getDate() {
                return new class {
                    public function toSql(): string { return date("Y-m-d H:i:s"); }
                };
            }
        }
    ');
}

// Stub Joomla\Registry\Registry if not available
if (!class_exists(\Joomla\Registry\Registry::class)) {
    eval('
        namespace Joomla\Registry;
        class Registry {
            private array $data;
            public function __construct(array|string $data = []) {
                if (is_string($data)) {
                    $data = json_decode($data, true) ?? [];
                }
                $this->data = $data;
            }
            public function get(string $path, $default = null) {
                return $this->data[$path] ?? $default;
            }
            public function set(string $path, $value): self {
                $this->data[$path] = $value;
                return $this;
            }
        }
    ');
}
