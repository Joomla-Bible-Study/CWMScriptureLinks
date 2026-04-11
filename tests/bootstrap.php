<?php

/**
 * PHPUnit bootstrap for CWM ScriptureLinks tests.
 *
 * Provides minimal Joomla stubs so plugin classes can be tested without
 * a full Joomla installation.  Mirrors the pattern used by the sibling
 * lib_cwmscripture test suite — if you change one, update both.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

if (!\defined('JPATH_LIBRARIES')) {
    \define('JPATH_LIBRARIES', __DIR__ . '/../libraries');
}

if (!\defined('JPATH_ADMINISTRATOR')) {
    \define('JPATH_ADMINISTRATOR', __DIR__ . '/stubs/administrator');
}

if (!class_exists(\Joomla\CMS\Language\Text::class)) {
    eval('
        namespace Joomla\CMS\Language;
        class Text {
            public static function _(string $key): string { return $key; }
            public static function sprintf(string $key, ...$args): string { return vsprintf($key, $args); }
        }
    ');
}

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
            public static array $entries = [];
            public static function add(string $message, int $priority = self::INFO, string $category = ""): void {
                self::$entries[] = compact("message", "priority", "category");
            }
            public static function addLogger(array $options, int $priorities = self::ALL, array $categories = []): void {}
        }
    ');
}

if (!class_exists(\Joomla\CMS\Factory::class)) {
    eval('
        namespace Joomla\CMS;
        class Factory {
            public static function getContainer() {
                return new class {
                    public function get(string $id) {
                        throw new \RuntimeException("No container available in test context: " . $id);
                    }
                };
            }
            public static function getApplication() { return new class {}; }
        }
    ');
}

if (!interface_exists(\Joomla\CMS\Extension\PluginInterface::class)) {
    eval('
        namespace Joomla\CMS\Extension;
        interface PluginInterface {}
    ');
}

if (!class_exists(\Joomla\CMS\Plugin\CMSPlugin::class)) {
    eval('
        namespace Joomla\CMS\Plugin;
        abstract class CMSPlugin implements \Joomla\CMS\Extension\PluginInterface {
            protected $params;
            protected $application;
            public function __construct($subject = null, array $config = []) {
                $this->params = new \Joomla\Registry\Registry($config["params"] ?? []);
            }
            public function setApplication($app): void { $this->application = $app; }
        }
    ');
}

if (!interface_exists(\Joomla\Event\SubscriberInterface::class)) {
    eval('
        namespace Joomla\Event;
        interface SubscriberInterface {
            public static function getSubscribedEvents(): array;
        }
    ');
}

if (!class_exists(\Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait::class)) {
    eval('
        namespace Joomla\Component\Scheduler\Administrator\Traits;
        trait TaskPluginTrait {
            public function advertiseRoutines($event) {}
            public function standardRoutineHandler($event) {}
            protected function logTask(string $message, string $priority = "info"): void {}
        }
    ');
}

if (!class_exists(\Joomla\Component\Scheduler\Administrator\Task\Status::class)) {
    eval('
        namespace Joomla\Component\Scheduler\Administrator\Task;
        class Status {
            public const OK = 0;
            public const KNOCKOUT = 3;
            public const NO_RUN = 4;
            public const NO_ROUTINE = 5;
            public const NO_EXIT = 6;
            public const NO_LOCK = 7;
            public const NO_RELEASE = 8;
            public const WILL_RESUME = 123;
            public const TIMEOUT = 124;
        }
    ');
}

if (!class_exists(\Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent::class)) {
    eval('
        namespace Joomla\Component\Scheduler\Administrator\Event;
        class ExecuteTaskEvent {
            private array $args;
            public function __construct(array $args = []) { $this->args = $args; }
            public function getArgument(string $name) { return $this->args[$name] ?? null; }
        }
    ');
}

if (!trait_exists(\Joomla\Database\DatabaseAwareTrait::class)) {
    eval('
        namespace Joomla\Database;
        trait DatabaseAwareTrait {
            protected $database;
            public function setDatabase($db): void { $this->database = $db; }
            public function getDatabase() { return $this->database; }
        }
    ');
}

if (!class_exists(\Joomla\Registry\Registry::class)) {
    eval('
        namespace Joomla\Registry;
        class Registry {
            private array $data;
            public function __construct(array|string $data = []) {
                if (is_string($data)) { $data = json_decode($data, true) ?? []; }
                $this->data = $data;
            }
            public function get(string $path, $default = null) { return $this->data[$path] ?? $default; }
            public function set(string $path, $value): self { $this->data[$path] = $value; return $this; }
        }
    ');
}
