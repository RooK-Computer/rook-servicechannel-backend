<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers shared core-domain service behavior.
 */
#[RunTestsInSeparateProcesses]
final class CoreDomainServicesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'rook_servicechannel_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('support_session');
    $this->installEntitySchema('terminal_grant');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('rook_servicechannel_core', [
      'rook_support_audit_log',
      'rook_support_session_participant',
    ]);
  }

  public function testLoadLatestSessionByPinReturnsNewestSession(): void {
    $manager = $this->container->get('rook_servicechannel_core.support_session_manager');

    $first_session = $manager->createSession('4711', '10.0.0.5');
    $second_session = $manager->createSession('4711', '10.0.0.6');

    $loaded_session = $manager->loadLatestSessionByPin('4711');

    self::assertNotNull($loaded_session);
    self::assertSame((int) $second_session->id(), (int) $loaded_session->id());
    self::assertNotSame((int) $first_session->id(), (int) $loaded_session->id());
  }

  public function testCoupleSessionIsIdempotentAndRefreshesLastSeen(): void {
    $session_manager = $this->container->get('rook_servicechannel_core.support_session_manager');
    $participant_manager = $this->container->get('rook_servicechannel_core.support_session_participant_manager');
    $database = $this->container->get('database');

    $session = $session_manager->createSession('4711', '10.0.0.5');
    $account = $this->createUser();

    $participant_manager->coupleSession($session, $account);
    self::assertTrue($participant_manager->isCoupled($session, $account));

    $participant_row = $database->select('rook_support_session_participant', 'participant')
      ->fields('participant', ['id', 'last_seen_at'])
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->execute()
      ->fetchAssoc();

    self::assertIsArray($participant_row);

    $database->update('rook_support_session_participant')
      ->fields(['last_seen_at' => (int) $participant_row['last_seen_at'] - 10])
      ->condition('id', (int) $participant_row['id'])
      ->execute();

    $participant_manager->coupleSession($session, $account);

    $refreshed_last_seen = $database->select('rook_support_session_participant', 'participant')
      ->fields('participant', ['last_seen_at'])
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->execute()
      ->fetchField();

    $participant_count = $database->select('rook_support_session_participant', 'participant')
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    self::assertSame(1, (int) $participant_count);
    self::assertGreaterThan((int) $participant_row['last_seen_at'] - 10, (int) $refreshed_last_seen);
  }

  public function testGrantLookupAndOutstandingGrantRevocation(): void {
    $session = $this->container->get('rook_servicechannel_core.support_session_manager')->createSession('4711', '10.0.0.5');
    $account = $this->createUser();
    $grant_manager = $this->container->get('rook_servicechannel_core.terminal_grant_manager');

    $first_grant = $grant_manager->issueGrant(
      $session,
      $account,
      '10.0.0.5',
      \Drupal::time()->getRequestTime() + 120,
      \Drupal::time()->getRequestTime() + 30,
    );
    $second_grant = $grant_manager->issueGrant(
      $session,
      $account,
      '10.0.0.5',
      \Drupal::time()->getRequestTime() + 120,
      \Drupal::time()->getRequestTime() + 30,
    );

    $loaded_grant = $grant_manager->loadGrantByToken($first_grant['token']);
    self::assertNotNull($loaded_grant);
    self::assertSame((int) $first_grant['grant']->id(), (int) $loaded_grant->id());

    $grant_manager->redeemGrant($second_grant['grant']);
    $grant_manager->revokeOutstandingGrants($session, $account);

    $reloaded_first_grant = $this->loadGrant((int) $first_grant['grant']->id());
    $reloaded_second_grant = $this->loadGrant((int) $second_grant['grant']->id());

    self::assertSame('revoked', (string) $reloaded_first_grant->get('status')->value);
    self::assertSame('revoked', (string) $reloaded_second_grant->get('status')->value);
  }

  public function testTimedOutSessionIsClosedThroughSharedManagerLogic(): void {
    $manager = $this->container->get('rook_servicechannel_core.support_session_manager');
    $session = $manager->createSession('4711', '10.0.0.5');

    $session->set('expires_at', \Drupal::time()->getRequestTime() - 1);
    $session->save();

    $session = $manager->expireSessionIfTimedOut($session);

    self::assertSame('closed', (string) $session->get('status')->value);
    self::assertSame('heartbeat_timeout', (string) $session->get('close_reason')->value);
  }

  public function testActiveSessionCanReturnToOpenWithoutClosing(): void {
    $manager = $this->container->get('rook_servicechannel_core.support_session_manager');
    $session = $manager->createSession('4711', '10.0.0.5');

    $session = $manager->activateSession($session);
    $session = $manager->markSessionOpen($session);

    self::assertSame('open', (string) $session->get('status')->value);
    self::assertSame(0, (int) $session->get('active_terminal_count')->value);
    self::assertSame('', (string) $session->get('close_reason')->value);
    self::assertSame('', (string) $session->get('closed_at')->value);
  }

  /**
   * Creates and saves a simple active user.
   */
  private function createUser(): User {
    $suffix = bin2hex(random_bytes(4));

    $user = User::create([
      'name' => 'core_user_' . $suffix,
      'mail' => 'core_user_' . $suffix . '@example.com',
      'status' => 1,
    ]);
    $user->save();

    return $user;
  }

  /**
   * Reloads a terminal grant entity by ID.
   */
  private function loadGrant(int $grantId) {
    return $this->container->get('entity_type.manager')
      ->getStorage('terminal_grant')
      ->load($grantId);
  }

}
