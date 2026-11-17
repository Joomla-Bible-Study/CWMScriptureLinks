<?php

/**
 * Who may call the plugin's AJAX actions.
 *
 * The translation-management actions checked only Joomla's CSRF token. Joomla
 * hands that token to every visitor on every page that loads its core script,
 * so the check let anyone call them from the site. These tests pin the gate the
 * dispatcher now puts in front of every action but one:
 * - the request must come from the administrator application;
 * - the user must be allowed to edit plugins.
 * getPassage, the scripture tooltip's read-only endpoint, stays public.
 *
 * The tests drive the real dispatcher with a stubbed application. Session's
 * stub answers checkToken() with Session::$tokenValid, so a test can model a
 * request that carries the page's token, as an attacker's would.
 *
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since  __DEPLOY_VERSION__
 */

namespace CWM\ScriptureLinks\Tests\Content;

use CWM\Plugin\Content\ScriptureLinks\Extension\ScriptureLinks;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @since __DEPLOY_VERSION__
 */
final class AjaxAccessTest extends TestCase
{
    /**
     * The rights of a user who can open and edit the plugin's settings.
     *
     * @var    list<string>
     * @since  __DEPLOY_VERSION__
     */
    private const PLUGIN_EDITOR = ['core.manage com_plugins', 'core.edit com_plugins'];

    /**
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    protected function tearDown(): void
    {
        Session::$tokenValid = false;
    }

    /**
     * Every action the dispatcher answers except getPassage.
     *
     * Read from the source, so an action added later is covered without
     * touching this test.
     *
     * @return  array<string, array{0: string}>
     * @since __DEPLOY_VERSION__
     */
    public static function managementActions(): array
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 3) . '/src/Extension/ScriptureLinks.php');

        $start = (int) strpos($source, '$dispatchers = [');
        $end   = (int) strpos($source, '];', $start);
        preg_match_all("/'([a-zA-Z]+)'\s*=>\s*'[a-zA-Z]+'/", substr($source, $start, $end - $start), $mapped);

        preg_match('/\$pluginActions = \[([^\]]*)\]/', $source, $list);
        preg_match_all("/'([a-zA-Z]+)'/", $list[1] ?? '', $posted);

        $actions = array_values(array_diff(array_merge($mapped[1], $posted[1]), ['getPassage']));

        return array_combine($actions, array_map(static fn (string $action): array => [$action], $actions));
    }

    /**
     * A user stub that records what it was asked to authorise.
     *
     * @param   list<string>  $grants  The rights it holds, as "action asset"
     * @param   bool          $guest   Whether the user is a guest
     *
     * @return  object
     * @since __DEPLOY_VERSION__
     */
    private static function user(array $grants = [], bool $guest = false): object
    {
        return new class ($grants, $guest) {
            /** @var list<array{0: string, 1: ?string}> */
            public array $asked = [];

            public function __construct(private readonly array $grants, public readonly bool $guest)
            {
            }

            public function authorise(string $action, ?string $asset = null): bool
            {
                $this->asked[] = [$action, $asset];

                return \in_array($action . ' ' . $asset, $this->grants, true);
            }
        };
    }

    /**
     * Send one action through the dispatcher and report how it answered.
     *
     * The raw JSON actions echo a reply and close the request; the POST actions
     * use com_ajax's pattern and throw. The application stub's close() throws,
     * standing in for the exit that ends a real request.
     *
     * @param   string   $action  The action requested
     * @param   string   $client  The application: 'site' or 'administrator'
     * @param   ?object  $user    The user, or null for no identity
     *
     * @return  array{kind: string, message: string, code: int}
     * @since __DEPLOY_VERSION__
     */
    private static function dispatch(string $action, string $client, ?object $user): array
    {
        $closed = new class ('closed') extends \RuntimeException {};

        $app = new class ($action, $client, $user, $closed) {
            public function __construct(
                private readonly string $action,
                private readonly string $client,
                private readonly ?object $user,
                private readonly \RuntimeException $closed
            ) {
            }

            public function isClient(string $name): bool
            {
                return $name === $this->client;
            }

            public function getIdentity(): ?object
            {
                return $this->user;
            }

            public function getInput(): object
            {
                return new class ($this->action) {
                    public function __construct(private readonly string $action)
                    {
                    }

                    public function getCmd(string $name, string $default = ''): string
                    {
                        return $name === 'action' ? $this->action : $default;
                    }

                    public function getString(string $name, string $default = ''): string
                    {
                        return $default;
                    }
                };
            }

            public function close($code = 0): void
            {
                throw $this->closed;
            }
        };

        $plugin = new ScriptureLinks(null, ['params' => ['default_version' => 'kjv', 'gdpr_mode' => 1]]);
        $plugin->setApplication($app);

        $thrown = null;

        ob_start();

        try {
            $plugin->onAjaxScripturelinks(new Event('onAjaxScripturelinks'));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertNotNull($thrown, $action . ' returned without answering: the request must be closed, or refused');

        if ($thrown !== $closed) {
            return ['kind' => 'exception', 'message' => $thrown->getMessage(), 'code' => (int) $thrown->getCode()];
        }

        $reply = json_decode($output, true);

        self::assertIsArray($reply, $action . ' must answer with JSON: ' . $output);

        return ['kind' => 'json', 'message' => (string) ($reply['message'] ?? ''), 'code' => 200];
    }

    /**
     * @param   string  $action  The action requested
     * @param   array   $reply   What dispatch() reported
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    private static function assertRefused(string $action, array $reply): void
    {
        self::assertSame(
            'JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN',
            $reply['message'],
            $action . ' must be refused before it runs, but answered: ' . json_encode($reply)
        );

        if ($reply['kind'] === 'exception') {
            self::assertSame(403, $reply['code']);
        }
    }

    /**
     * @param   string  $action  The action requested
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[DataProvider('managementActions')]
    #[TestDox('⚠️ A site visitor holding the page\'s token is refused: $action')]
    public function testSiteVisitorWithTheTokenIsRefused(string $action): void
    {
        // Every frontend page hands its visitor the session's token, so this
        // models anyone at all. Without the gate, the action would run.
        Session::$tokenValid = true;

        self::assertRefused($action, self::dispatch($action, 'site', self::user(guest: true)));
    }

    /**
     * @param   string  $action  The action requested
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[DataProvider('managementActions')]
    #[TestDox('A logged-in site user is refused, even one who may edit plugins: $action')]
    public function testSiteUserIsRefusedWhateverTheirRights(string $action): void
    {
        Session::$tokenValid = true;

        self::assertRefused($action, self::dispatch($action, 'site', self::user(self::PLUGIN_EDITOR)));
    }

    /**
     * @param   string  $action  The action requested
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[DataProvider('managementActions')]
    #[TestDox('An administrator with no rights over plugins is refused: $action')]
    public function testAdministratorWithoutPluginRightsIsRefused(string $action): void
    {
        Session::$tokenValid = true;

        self::assertRefused($action, self::dispatch($action, 'administrator', self::user()));
    }

    /**
     * Each right alone, without the other.
     *
     * @return  array<string, array{0: list<string>}>
     * @since __DEPLOY_VERSION__
     */
    public static function partialRights(): array
    {
        return [
            // Joomla's default Manager group: core.edit is granted site-wide,
            // core.manage only to Administrators, so Managers can't open the
            // plugin manager. core.edit alone must not be enough.
            'edit only, as a default Manager' => [['core.edit com_plugins']],
            'manage only'                     => [['core.manage com_plugins']],
        ];
    }

    /**
     * @param   list<string>  $grants  The rights the administrator holds
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[DataProvider('partialRights')]
    #[TestDox('⚠️ An administrator who cannot both open and edit plugins is refused ($_dataName)')]
    public function testPartialPluginRightsAreRefused(array $grants): void
    {
        Session::$tokenValid = true;

        self::assertRefused('removeAllTranslations', self::dispatch('removeAllTranslations', 'administrator', self::user($grants)));
    }

    /**
     * @param   string  $action  The action requested
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[DataProvider('managementActions')]
    #[TestDox('An administrator who may edit plugins gets through, and the token is still checked: $action')]
    public function testAdministratorWhoMayEditPluginsReachesTheAction(string $action): void
    {
        // The token is invalid, so the action stops at its own token check.
        // Reaching that check shows the gate let the user through.
        Session::$tokenValid = false;
        $user                = self::user(self::PLUGIN_EDITOR);

        $reply = self::dispatch($action, 'administrator', $user);

        self::assertContains($reply['message'], ['JINVALID_TOKEN', 'Invalid token'], $action . ' must reach its token check: ' . json_encode($reply));
        self::assertSame([['core.manage', 'com_plugins'], ['core.edit', 'com_plugins']], $user->asked, 'The gate asks for both rights on com_plugins');
    }

    /**
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[TestDox('⚠️ The scripture tooltip stays public: a site visitor reaches getPassage')]
    public function testTooltipStaysPublic(): void
    {
        $reply = self::dispatch('getPassage', 'site', self::user(guest: true));

        self::assertSame('json', $reply['kind']);
        self::assertSame('Not a scripture reference', $reply['message'], 'The tooltip endpoint must have run');
    }

    /**
     * A scan that finds nothing proves nothing.
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[TestDox('The action list was read from the source, and covers both kinds of action')]
    public function testActionListIsNotVacuous(): void
    {
        $actions = array_keys(self::managementActions());

        self::assertGreaterThanOrEqual(12, \count($actions));
        self::assertNotContains('getPassage', $actions);

        foreach (['removeAllTranslations', 'downloadTranslation', 'syncApiBible', 'saveParams', 'download', 'remove'] as $action) {
            self::assertContains($action, $actions);
        }
    }
}
