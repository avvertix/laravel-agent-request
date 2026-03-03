<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest;

use Illuminate\Http\Request;

class WebBotAuthVerifier
{
    public function __construct(protected readonly Request $request) {}

    /**
     * Create a new instance from the given request.
     */
    public static function fromRequest(Request $request): static
    {
        // @phpstan-ignore new.static
        return new static($request);
    }

    /**
     * Verify the Web Bot Auth cryptographic signature carried on this request.
     *
     * Performs full verification per draft-meunier-web-bot-auth-architecture:
     *  1. Parses Signature-Agent, Signature-Input, and Signature headers.
     *  2. Validates metadata: tag must be "web-bot-auth", expiry must be in the future.
     *  3. Fetches the Ed25519 public-key directory from the Signature-Agent URL over HTTPS.
     *  4. Finds the key whose JWK thumbprint matches the keyid parameter.
     *  5. Reconstructs the signature base per RFC 9421.
     *  6. Verifies the Ed25519 signature against the resolved public key.
     *
     * Returns false on any parsing, validation, network, or cryptographic failure.
     * Requires the PHP sodium extension (bundled since PHP 7.2).
     *
     * The key directory fetch is performed by fetchKeyDirectory(), which can be
     * overridden in a subclass to inject a custom HTTP client.
     *
     * @see https://datatracker.ietf.org/doc/html/draft-meunier-web-bot-auth-architecture
     * @see https://www.rfc-editor.org/rfc/rfc9421
     */
    public function verify(): bool
    {
        if (! extension_loaded('sodium')) {
            return false;
        }

        $agentUrl = $this->parseSignatureAgent();
        if ($agentUrl === null) {
            return false;
        }

        $sigInput = $this->parseSignatureInput();
        if ($sigInput === null) {
            return false;
        }

        $params = $sigInput['params'];

        if (($params['tag'] ?? '') !== 'web-bot-auth') {
            return false;
        }

        if (! isset($params['keyid'], $params['expires'], $params['created'])) {
            return false;
        }

        if ($params['expires'] <= time()) {
            return false;
        }

        $signatureBytes = $this->parseSignatureBytes($sigInput['label']);
        if ($signatureBytes === null) {
            return false;
        }

        $keyDirectoryUrl = rtrim($agentUrl, '/').'/.well-known/http-message-signatures-directory';
        $keys = $this->fetchKeyDirectory($keyDirectoryUrl);
        if ($keys === null) {
            return false;
        }

        $publicKey = $this->findPublicKey($keys, (string) $params['keyid']);
        if ($publicKey === null) {
            return false;
        }

        $signatureBase = $this->buildSignatureBase($sigInput['components'], $sigInput['signatureParams']);

        return sodium_crypto_sign_verify_detached($signatureBytes, $signatureBase, $publicKey);
    }

    /**
     * Parse the Signature-Agent structured-field header and return the https:// URL,
     * or null if the header is absent, malformed, or not an https:// URI.
     *
     * The header value is an sf-string (RFC 8941 quoted string). Cloudflare requires
     * the value to be enclosed in double quotes and start with https://.
     *
     * @see https://www.ietf.org/archive/id/draft-meunier-http-message-signatures-directory-01.html
     */
    protected function parseSignatureAgent(): ?string
    {
        $header = $this->request->header('Signature-Agent');
        if ($header === null) {
            return null;
        }

        // sf-string: a double-quoted string with no unescaped double-quotes inside.
        if (preg_match('/^"(https:\/\/[^"]+)"$/', trim($header), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Parse the Signature-Input header into its constituent parts.
     *
     * Returns an array with:
     *   - label:           the signature label (e.g. "sig1")
     *   - components:      ordered list of covered component identifiers
     *   - params:          map of parameter name => string|int value
     *   - signatureParams: the raw value after "label=" for the @signature-params line
     *
     * Only the first (or only) signature entry is parsed. Returns null on any failure.
     *
     * @return array{label:string,components:list<string>,params:array<string,string|int>,signatureParams:string}|null
     */
    protected function parseSignatureInput(): ?array
    {
        $raw = $this->request->header('Signature-Input');
        if ($raw === null) {
            return null;
        }

        // label=(<components>)<params>
        if (! preg_match('/^(\w+)=(.+)$/s', trim($raw), $outer)) {
            return null;
        }

        $label = $outer[1];
        $signatureParamsValue = $outer[2]; // captured verbatim for @signature-params

        // Extract the inner component list from (...)
        if (! preg_match('/^\(([^)]*)\)(.*)/s', $signatureParamsValue, $inner)) {
            return null;
        }

        // Component identifiers are double-quoted strings inside the parens.
        preg_match_all('/"([^"]+)"/', $inner[1], $compMatch);
        $components = $compMatch[1];

        // Parameters: ;name=<sf-integer> or ;name="<sf-string>"
        $params = [];
        preg_match_all('/;([a-z][a-z0-9-]*)=(?:"([^"]*?)"|(-?\d+))/', $inner[2], $paramMatches, PREG_SET_ORDER);
        foreach ($paramMatches as $m) {
            // Group 3 exists only when the integer alternative matched (it's absent for the string branch).
            $params[$m[1]] = isset($m[3]) ? (int) $m[3] : $m[2];
        }

        return [
            'label' => $label,
            'components' => $components,
            'params' => $params,
            'signatureParams' => $signatureParamsValue,
        ];
    }

    /**
     * Extract the raw signature bytes for the given label from the Signature header.
     *
     * The Signature header format is an sf-dictionary of sf-binary items:
     *   label=:<base64>:
     *
     * @see https://www.rfc-editor.org/rfc/rfc9421#name-the-signature-http-field
     */
    protected function parseSignatureBytes(string $label): ?string
    {
        $raw = $this->request->header('Signature');
        if ($raw === null) {
            return null;
        }

        // sf-binary items are delimited by colons: label=:<base64>:
        if (! preg_match('/'.preg_quote($label, '/').'=:([A-Za-z0-9+\/=]+):/', $raw, $matches)) {
            return null;
        }

        $bytes = base64_decode($matches[1], true);

        return $bytes !== false ? $bytes : null;
    }

    /**
     * Reconstruct the signature base string per RFC 9421 Section 2.5.
     *
     * Each covered component is serialized as one line:
     *   "<component-id>": <value>\n
     *
     * The final line has no trailing newline:
     *   "@signature-params": <signatureParamsValue>
     *
     * @param  list<string>  $components  ordered component identifiers from Signature-Input
     * @param  string  $signatureParamsValue  raw value captured from Signature-Input (after "label=")
     *
     * @see https://www.rfc-editor.org/rfc/rfc9421#section-2.5
     */
    protected function buildSignatureBase(array $components, string $signatureParamsValue): string
    {
        $base = '';

        foreach ($components as $component) {
            $base .= '"'.$component.'": '.$this->getComponentValue($component)."\n";
        }

        $base .= '"@signature-params": '.$signatureParamsValue;

        return $base;
    }

    /**
     * Resolve the value of a single signature component from the current request.
     *
     * Derived components (prefixed "@") are computed from request metadata.
     * All other identifiers are treated as HTTP header field names (case-insensitive).
     * Multiple header field values are joined with ", " per RFC 9421.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9421#section-2.1
     * @see https://www.rfc-editor.org/rfc/rfc9421#section-2.2
     */
    protected function getComponentValue(string $component): string
    {
        return match ($component) {
            // RFC 9421 §2.2.3 — host[:port], port omitted for standard 80/443
            '@authority' => $this->request->getHttpHost(),
            '@method' => $this->request->method(),
            '@path' => $this->request->getPathInfo(),
            '@query' => '?'.($this->request->getQueryString() ?? ''),
            '@target-uri' => $this->request->url(),
            '@scheme' => $this->request->getScheme(),
            // HTTP header field: use raw value as-is (including any structured-field quoting)
            default => $this->request->header($component) ?? '',
        };
    }

    /**
     * Fetch and decode the JWKS from the agent's key directory URL.
     *
     * The directory is served at /.well-known/http-message-signatures-directory
     * per draft-meunier-http-message-signatures-directory.
     *
     * This method can be overridden in a subclass to inject a custom HTTP client
     * (e.g. Laravel's Http facade or Guzzle).
     *
     * @return list<array<string,string>>|null the "keys" array from the JWKS, or null on failure
     *
     * @see https://datatracker.ietf.org/doc/html/draft-meunier-http-message-signatures-directory
     */
    protected function fetchKeyDirectory(string $url): ?array
    {
        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "Accept: application/http-message-signatures-directory+json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (! is_array($data) || ! isset($data['keys']) || ! is_array($data['keys'])) {
            return null;
        }

        return $data['keys'];
    }

    /**
     * Find the Ed25519 public key in the JWKS whose JWK thumbprint matches keyid.
     * Returns the raw 32-byte key for use with sodium, or null if not found.
     *
     * Only OKP / Ed25519 keys are considered; all other key types are skipped.
     *
     * @param  list<array<string,string>>  $keys  the "keys" array from the JWKS response
     * @param  string  $keyid  base64url-encoded SHA-256 JWK thumbprint (RFC 8037)
     */
    protected function findPublicKey(array $keys, string $keyid): ?string
    {
        foreach ($keys as $jwk) {
            if (
                ! isset($jwk['kty'], $jwk['crv'], $jwk['x'])
                || $jwk['kty'] !== 'OKP'
                || $jwk['crv'] !== 'Ed25519'
            ) {
                continue;
            }

            if ($this->computeJwkThumbprint($jwk) !== $keyid) {
                continue;
            }

            $bytes = $this->base64urlDecode($jwk['x']);

            // Ed25519 public keys are always exactly 32 bytes.
            if ($bytes === false || strlen($bytes) !== 32) {
                continue;
            }

            return $bytes;
        }

        return null;
    }

    /**
     * Compute the base64url-encoded JWK thumbprint for an Ed25519 (OKP) key.
     *
     * The canonical member set for OKP keys is {crv, kty, x} in lexicographic order,
     * serialized as a compact JSON object, then SHA-256 hashed and base64url-encoded.
     *
     * @param  array<string,string>  $jwk
     *
     * @see https://www.rfc-editor.org/rfc/rfc8037#appendix-A.3
     * @see https://www.rfc-editor.org/rfc/rfc7638
     */
    protected function computeJwkThumbprint(array $jwk): string
    {
        // Keys must appear in lexicographic order with no extra whitespace.
        $canonical = json_encode(
            ['crv' => $jwk['crv'], 'kty' => $jwk['kty'], 'x' => $jwk['x']],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return rtrim(strtr(base64_encode(hash('sha256', $canonical, true)), '+/', '-_'), '=');
    }

    /**
     * Decode a base64url string to raw bytes.
     * Handles both padded and unpadded input (RFC 7517 JWK values omit padding).
     */
    protected function base64urlDecode(string $input): string|false
    {
        $padded = str_pad(
            strtr($input, '-_', '+/'),
            strlen($input) + (4 - strlen($input) % 4) % 4,
            '=',
        );

        return base64_decode($padded, true);
    }
}
