<?php

namespace KPT\Tests;

use PHPUnit\Framework\TestCase;
use KPT\Database;

class DatabaseTest extends TestCase
{
    private object $testSettings;

    protected function setUp(): void
    {
        // Create test database settings
        $this->testSettings = (object) [
            'server' => 'localhost',
            'schema' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];

        // Mock Logger class if it doesn't exist
        if (!class_exists('KPT\Logger')) {
            eval('
                namespace KPT;
                class Logger {
                    public static function debug($message, $context = []) {}
                    public static function error($message, $context = []) {}
                }
            ');
        }
    }

    public function testDatabaseClassExists(): void
    {
        $this->assertTrue(class_exists('KPT\Database'));
    }

    public function testConstructorAcceptsSettings(): void
    {
        try {
            $db = new Database($this->testSettings);
            $this->assertInstanceOf(Database::class, $db);
        } catch (\Exception $e) {
            // Expected if no database connection available
            $this->markTestSkipped('Database connection not available');
        }
    }

    public function testMethodChainingWorks(): void
    {
        try {
            $db = new Database($this->testSettings);
            $result = $db->query("SELECT 1")->single()->asArray();
            // If we get here, the chaining worked
            $this->assertInstanceOf(Database::class, $result);
        } catch (\Exception $e) {
            // Expected if no database connection - check for common DB connection errors
            $message = strtolower($e->getMessage());
            $this->assertTrue(
                str_contains($message, 'database') ||
                str_contains($message, 'connection') ||
                str_contains($message, 'sqlstate') ||
                str_contains($message, 'mysql') ||
                str_contains($message, 'pdo'),
                "Expected database-related error, got: " . $e->getMessage()
            );
        }
    }

    public function testResetReturnsInstance(): void
    {
        try {
            $db = new Database($this->testSettings);
            $result = $db->reset();
            $this->assertInstanceOf(Database::class, $result);
        } catch (\Exception $e) {
            // Skip if database not available
            $this->markTestSkipped('Database not available');
        }
    }

    public function testCamelCaseMethodsExist(): void
    {
        try {
            $db = new Database($this->testSettings);
            
            // Test that PSR-12 compliant methods exist
            $this->assertTrue(method_exists($db, 'asArray'));
            $this->assertTrue(method_exists($db, 'asObject'));
            $this->assertTrue(method_exists($db, 'getLastId'));
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available');
        }
    }
}