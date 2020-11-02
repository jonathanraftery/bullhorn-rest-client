<?php

namespace jonathanraftery\Bullhorn\Rest\Tests\Unit {
    use Exception;
    use jonathanraftery\Bullhorn\Rest\Auth\Exception\DataStoreException;
    use jonathanraftery\Bullhorn\Rest\Auth\Store\WordpressDataStore;
    use PHPUnit\Framework\TestCase;
    use function setupMockWordpressContext;

    final class WordpressDataStoreTest extends TestCase
    {
        function test_wordpressExceptionOutsideOfWordpressContext()
        {
            $this->expectException(DataStoreException::class);
            new WordpressDataStore();
        }

        function test_worksWithinWordpressContext()
        {
            try {
                setupMockWordpressContext();
                new WordpressDataStore();
                $this->assertTrue(true);
            } catch (Exception $e) {
                $this->assertTrue(false);
            }
        }
    }
}

namespace {
    // we need to put mock WP functions in the global namespace
    function setupMockWordpressContext() {
        function get_option() {}
        function update_option() {}
    }
}
