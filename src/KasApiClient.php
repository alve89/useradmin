<?php

declare(strict_types=1);

final class KasApiClient
{
    private array $config;
    private array $kasFloodDelays = [];
    private array $kasLastRequestAt = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function updateMailPassword(string $mailLogin, string $newPassword, string $session2fa = ''): void
    {
        $kasConfig = $this->config['kas'] ?? [];

        if (($kasConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException('KAS-API ist in der Config nicht aktiviert.');
        }

        if (!class_exists(SoapClient::class)) {
            throw new RuntimeException('PHP SOAP Extension ist nicht verfügbar.');
        }

        $kasLogin = (string)($kasConfig['login'] ?? '');
        $kasPassword = (string)($kasConfig['password'] ?? '');
        $authWsdl = (string)($kasConfig['auth_wsdl'] ?? '');
        $apiWsdl = (string)($kasConfig['api_wsdl'] ?? '');
        $authType = (string)($kasConfig['auth_type'] ?? 'session');

        if ($kasLogin === '' || $kasPassword === '' || $authWsdl === '' || $apiWsdl === '') {
            throw new RuntimeException('KAS-API-Konfiguration ist unvollständig.');
        }

        if ($mailLogin === '') {
            throw new RuntimeException('mailLogin fehlt.');
        }

        if ($newPassword === '') {
            throw new RuntimeException('Neues Passwort fehlt.');
        }

        if ($authType === 'plain') {
            $this->callApiPlain($kasLogin, $kasPassword, $apiWsdl, $mailLogin, $newPassword);
            return;
        }

if ($authType === 'session') {
    $token = $this->createSessionToken($kasLogin, $kasPassword, $authWsdl, $session2fa);

    $resolvedMailLogin = $this->resolveMailLogin(
        $kasLogin,
        $token,
        $apiWsdl,
        $mailLogin
    );

    $this->callApiSession($kasLogin, $token, $apiWsdl, $resolvedMailLogin, $newPassword);
    return;
}

        throw new RuntimeException('Nicht unterstützter KAS auth_type: ' . $authType);
    }

    private function createSessionToken(string $kasLogin, string $kasPassword, string $authWsdl, string $session2fa = ''): string
    {
        $params = [
            'kas_login' => $kasLogin,
            'kas_auth_type' => 'plain',
            'kas_auth_data' => $kasPassword,
            'session_lifetime' => 600,
            'session_update_lifetime' => 'N',
        ];

        $session2fa = trim($session2fa);

        if ($session2fa !== '') {
            $params['session_2fa'] = $session2fa;
        }

        try {
            usleep(900000);
            $client = new SoapClient($authWsdl, [
                'trace' => false,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ]);

            $token = $client->KasAuth(json_encode($params));
            usleep(700000);

            if (!is_string($token) || $token === '') {
                throw new RuntimeException('KAS-Authentifizierung lieferte keinen Sessiontoken.');
            }

            Logger::info($this->config, 'KAS session token created');

            return $token;
        } catch (SoapFault $fault) {
            Logger::error($this->config, 'KAS SOAP auth error', [
                'faultcode' => $fault->faultcode ?? null,
                'faultstring' => $fault->faultstring ?? null,
            ]);

            $faultString = (string)($fault->faultstring ?? $fault->getMessage());

            throw new UserVisibleException($this->mapKasFaultToUserMessage($faultString), 0, $fault);
        }
    }

    private function callApiSession(
        string $kasLogin,
        string $sessionToken,
        string $apiWsdl,
        string $mailLogin,
        string $newPassword
    ): void {
        $params = [
            'kas_login' => $kasLogin,
            'kas_auth_type' => 'session',
            'kas_auth_data' => $sessionToken,
            'kas_action' => 'update_mailaccount',
            'KasRequestParams' => [
                'mail_login' => $mailLogin,
                'mail_new_password' => $newPassword,
            ],
        ];

        $this->callKasApi($apiWsdl, $params, $mailLogin);
    }

    private function callApiPlain(
        string $kasLogin,
        string $kasPassword,
        string $apiWsdl,
        string $mailLogin,
        string $newPassword
    ): void {
        $params = [
            'kas_login' => $kasLogin,
            'kas_auth_type' => 'plain',
            'kas_auth_data' => $kasPassword,
            'kas_action' => 'update_mailaccount',
            'KasRequestParams' => [
                'mail_login' => $mailLogin,
                'mail_new_password' => $newPassword,
            ],
        ];

        $this->callKasApi($apiWsdl, $params, $mailLogin);
    }

private function callKasApi(string $apiWsdl, array $params, string $mailLogin): void
{
    $result = $this->kasApiRequest(
        $apiWsdl,
        $params,
        'KAS SOAP error while updating mail password',
        ['mail_login' => $mailLogin]
    );

    Logger::info($this->config, 'KAS mail password updated', [
        'mail_login' => $mailLogin,
        'result' => is_scalar($result) ? (string)$result : gettype($result),
    ]);
}



private function mapKasFaultToUserMessage(string $faultString): string
{
    return match ($faultString) {
        'flood_protection' =>
            'Die KAS-API hat zu viele Anfragen in kurzer Zeit blockiert. Bitte warte kurz und versuche es erneut.',
        'otp_pin_incorrect' =>
            'Der eingegebene KAS-2FA-Code ist falsch oder abgelaufen. Bitte gib einen aktuellen Code ein und versuche es erneut.',

        'in_progress' =>
            'Das Mailkonto wird bei KAS noch verarbeitet. Bitte warte kurz und versuche es erneut.',

        'kas_password_incorrect' =>
            'Die KAS-Zugangsdaten in der Konfiguration sind falsch. Bitte prüfe KAS-Login und KAS-Passwort.',

        'kas_auth_type_disabled' =>
            'Die gewählte KAS-Authentifizierung ist für diesen Account deaktiviert. Bitte Session-Authentifizierung verwenden.',

        'mail_login_not_found' =>
            'Der angegebene KAS-Mail-Login wurde nicht gefunden. Vermutlich ist die E-Mail-Adresse nicht der KAS-interne Mailbox-Login.',

        'local_part_syntax_incorrect' =>
            'Der Teil vor dem @ der E-Mail-Adresse ist für KAS ungültig.',

        'nothing_to_do' =>
            'KAS hat keine Änderung vorgenommen, weil die gewünschten Werte bereits gesetzt sind.',


        'domain_part_syntax_incorrect' =>
            'Die Domain der E-Mail-Adresse ist für KAS ungültig.',

        'mailaccount_already_exists' =>
            'Für diese E-Mail-Adresse existiert bereits ein Mailkonto.',

        'mailaddress_already_exists' =>
            'Für diese E-Mail-Adresse existiert bereits ein Mailkonto oder Alias.',

        'mail_password_syntax_incorrect' =>
            'Das Mailpasswort erfüllt die KAS-Anforderungen nicht.',

        'unknown_action' =>
            'Die KAS-Aktion zum Anlegen eines Postfachs wurde nicht akzeptiert. Bitte prüfe den Aktionsnamen add_mailaccount in der KAS-Dokumentation.',

        'login_not_found' =>
            'Der KAS-Mailaccount wurde nicht gefunden.',

        'mail_login_not_found' =>
            'Der KAS-Mailaccount wurde nicht gefunden.',

        'mail_login_syntax_incorrect' =>
            'Der KAS-Mail-Login ist syntaktisch ungültig.',


        default =>
            'KAS-API-Fehler: ' . $faultString,
    };
}



private function resolveMailLogin(
    string $kasLogin,
    string $sessionToken,
    string $apiWsdl,
    string $identifier
): string {
    $identifier = trim(strtolower($identifier));

    if ($identifier === '') {
        throw new UserVisibleException('Es wurde kein Mailkonto angegeben.');
    }

    $accounts = $this->getMailaccounts($kasLogin, $sessionToken, $apiWsdl);

    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }

        $mailLogin = strtolower((string)($account['mail_login'] ?? ''));

        $addressFields = [
            (string)($account['mail_addresses'] ?? ''),
            (string)($account['mail_adresses'] ?? ''),
            (string)($account['mail_address'] ?? ''),
            (string)($account['mail_adress'] ?? ''),
            (string)($account['mail_email'] ?? ''),
            (string)($account['email'] ?? ''),
        ];

        if ($identifier === $mailLogin && $mailLogin !== '') {
            return (string)$account['mail_login'];
        }

        foreach ($addressFields as $addressField) {
            $addresses = array_map(
                static fn($value) => trim(strtolower($value)),
                explode(',', $addressField)
            );

            if (in_array($identifier, $addresses, true) && $mailLogin !== '') {
                Logger::info($this->config, 'Resolved KAS mail login', [
                    'identifier' => $identifier,
                    'mail_login' => (string)$account['mail_login'],
                ]);

                return (string)$account['mail_login'];
            }
        }
    }

    throw new UserVisibleException(
        'Für diese E-Mail-Adresse wurde in KAS kein passendes Mailkonto gefunden. Bitte prüfe, ob die Adresse wirklich als Mailbox existiert und nicht nur ein Alias ist.'
    );
}


private function getMailaccounts(string $kasLogin, string $sessionToken, string $apiWsdl): array
{
    $params = [
        'kas_login' => $kasLogin,
        'kas_auth_type' => 'session',
        'kas_auth_data' => $sessionToken,
        'kas_action' => 'get_mailaccounts',
        'KasRequestParams' => [],
    ];

    try {
        $result = $this->kasApiRequest(
            $apiWsdl,
            $params,
            'KAS SOAP error while loading mailaccounts'
        );

        if (is_object($result)) {
            $result = json_decode(json_encode($result), true) ?: [];
        }

        if (!is_array($result)) {
            return [];
        }

        if (isset($result['Response']['ReturnInfo']) && is_array($result['Response']['ReturnInfo'])) {
            return $result['Response']['ReturnInfo'];
        }

        if (isset($result['ReturnInfo']) && is_array($result['ReturnInfo'])) {
            return $result['ReturnInfo'];
        }

        return $result;
    } catch (SoapFault $fault) {
        Logger::error($this->config, 'KAS SOAP error while loading mailaccounts', [
            'faultcode' => $fault->faultcode ?? null,
            'faultstring' => $fault->faultstring ?? null,
        ]);

        $faultString = (string)($fault->faultstring ?? $fault->getMessage());

        throw new UserVisibleException($this->mapKasFaultToUserMessage($faultString), 0, $fault);
    }
}

public function createMailAccount(string $mailAddress, string $newPassword, string $session2fa = ''): void
{
    $kasConfig = $this->config['kas'] ?? [];

    if (($kasConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('KAS-API ist in der Config nicht aktiviert.');
    }

    if (!class_exists(SoapClient::class)) {
        throw new RuntimeException('PHP SOAP Extension ist nicht verfügbar.');
    }

    $kasLogin = (string)($kasConfig['login'] ?? '');
    $kasPassword = (string)($kasConfig['password'] ?? '');
    $authWsdl = (string)($kasConfig['auth_wsdl'] ?? '');
    $apiWsdl = (string)($kasConfig['api_wsdl'] ?? '');
    $authType = (string)($kasConfig['auth_type'] ?? 'session');

    if ($kasLogin === '' || $kasPassword === '' || $authWsdl === '' || $apiWsdl === '') {
        throw new RuntimeException('KAS-API-Konfiguration ist unvollständig.');
    }

    $mailAddress = trim(strtolower($mailAddress));

    if ($mailAddress === '') {
        throw new UserVisibleException('Es wurde keine E-Mail-Adresse für das neue Postfach angegeben.');
    }

    if ($newPassword === '') {
        throw new UserVisibleException('Bitte gib ein Passwort für das neue Postfach ein.');
    }

    if ($authType !== 'session') {
        throw new RuntimeException('Für das Anlegen von Postfächern wird aktuell auth_type=session erwartet.');
    }

    $token = $this->createSessionToken($kasLogin, $kasPassword, $authWsdl, $session2fa);

    $existingMailLogin = $this->tryResolveMailLogin(
        $kasLogin,
        $token,
        $apiWsdl,
        $mailAddress
    );

    if ($existingMailLogin !== null) {
        Logger::info($this->config, 'KAS mail account already exists, updating password instead', [
            'mail_address' => $mailAddress,
            'mail_login' => $existingMailLogin,
        ]);

        $this->callApiSession(
            $kasLogin,
            $token,
            $apiWsdl,
            $existingMailLogin,
            $newPassword
        );

        $this->updateMailAccountSpamfilter(
            $kasLogin,
            $token,
            $apiWsdl,
            $existingMailLogin
        );

        return;
    }

    $this->callCreateMailAccountSession($kasLogin, $token, $apiWsdl, $mailAddress, $newPassword);
    
    $resolvedMailLogin = $this->waitForMailAccountReady(
        $kasLogin,
        $token,
        $apiWsdl,
        $mailAddress
    );

    $this->updateMailAccountSpamfilter(
        $kasLogin,
        $token,
        $apiWsdl,
        $resolvedMailLogin
    );
}



private function callCreateMailAccountSession(
    string $kasLogin,
    string $sessionToken,
    string $apiWsdl,
    string $mailAddress,
    string $newPassword
): void {
    $mailAddress = trim(strtolower($mailAddress));

    if (!filter_var($mailAddress, FILTER_VALIDATE_EMAIL)) {
        throw new UserVisibleException('Die angegebene E-Mail-Adresse ist ungültig.');
    }

    [$localPart, $domainPart] = explode('@', $mailAddress, 2);

    if ($localPart === '' || $domainPart === '') {
        throw new UserVisibleException('Die E-Mail-Adresse konnte nicht in local_part und domain_part aufgeteilt werden.');
    }

    $params = [
        'kas_login' => $kasLogin,
        'kas_auth_type' => 'session',
        'kas_auth_data' => $sessionToken,
        'kas_action' => 'add_mailaccount',
        'KasRequestParams' => [
            'mail_password' => $newPassword,
            'local_part' => $localPart,
            'domain_part' => $domainPart,
            'webmail_autologin' => 'Y',
            'mail_xlist_enabled' => 'Y',
            'mail_xlist_sent' => 'Gesendet',
            'mail_xlist_drafts' => 'Entwürfe',
            'mail_xlist_trash' => 'Papierkorb',
            'mail_xlist_spam' => 'Spam',
            'mail_xlist_archiv' => 'Archiv',
        ],
    ];

    try {
        $result = $this->kasApiRequest(
            $apiWsdl,
            $params,
            'KAS SOAP error while creating mail account',
            ['mail_address' => $mailAddress]
        );

        Logger::info($this->config, 'KAS mail account created', [
            'mail_address' => $mailAddress,
            'result' => is_scalar($result) ? (string)$result : gettype($result),
        ]);

        Logger::info($this->config, 'KAS mail account created', [
            'mail_address' => $mailAddress,
            'result' => is_scalar($result) ? (string)$result : gettype($result),
        ]);
    } catch (SoapFault $fault) {
        Logger::error($this->config, 'KAS SOAP error while creating mail account', [
            'mail_address' => $mailAddress,
            'faultcode' => $fault->faultcode ?? null,
            'faultstring' => $fault->faultstring ?? null,
        ]);

        $faultString = (string)($fault->faultstring ?? $fault->getMessage());

        throw new UserVisibleException($this->mapKasFaultToUserMessage($faultString), 0, $fault);
    }
}

private function updateMailAccountSpamfilter(
    string $kasLogin,
    string $sessionToken,
    string $apiWsdl,
    string $mailLogin
): void {
    $params = [
        'kas_login' => $kasLogin,
        'kas_auth_type' => 'session',
        'kas_auth_data' => $sessionToken,
        'kas_action' => 'update_mailaccount',
        'KasRequestParams' => [
            'mail_login' => $mailLogin,
            'mail_spamfilter' => 'pdw,sf,ef',
        ],
    ];

    try {
        $result = $this->kasApiRequest(
            $apiWsdl,
            $params,
            'KAS SOAP error while updating mail spamfilter',
            [
                'mail_login' => $mailLogin,
                'spamfilter' => 'pdw,sf,ef',
            ]
        );

        Logger::info($this->config, 'KAS mail spamfilter updated', [
            'mail_login' => $mailLogin,
            'spamfilter' => 'pdw,sf,ef',
            'result' => is_scalar($result) ? (string)$result : gettype($result),
        ]);

        Logger::info($this->config, 'KAS mail spamfilter updated', [
            'mail_login' => $mailLogin,
            'spamfilter' => 'pdw,sf,ef',
            'result' => is_scalar($result) ? (string)$result : gettype($result),
        ]);
    } catch (SoapFault $fault) {
        Logger::error($this->config, 'KAS SOAP error while updating mail spamfilter', [
            'mail_login' => $mailLogin,
            'faultcode' => $fault->faultcode ?? null,
            'faultstring' => $fault->faultstring ?? null,
        ]);

        $faultString = (string)($fault->faultstring ?? $fault->getMessage());

        throw new UserVisibleException($this->mapKasFaultToUserMessage($faultString), 0, $fault);
    }
}


private function kasApiRequest(string $apiWsdl, array $params, string $logAction, array $logContext = [])
{
    $maxAttempts = 4;

    $action = (string)($params['kas_action'] ?? 'unknown');
    $key = $action;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $this->waitForKasFloodDelay($key);

            $client = new SoapClient($apiWsdl, [
                'trace' => false,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ]);

            $result = $client->KasApi(json_encode($params));

            $this->rememberKasFloodDelay($key, $result);

            return $result;
        } catch (SoapFault $fault) {
            $faultString = (string)($fault->faultstring ?? $fault->getMessage());

            Logger::error($this->config, $logAction, array_merge($logContext, [
                'faultcode' => $fault->faultcode ?? null,
                'faultstring' => $faultString,
                'attempt' => $attempt,
                'kas_action' => $action,
            ]));

            if ($faultString === 'nothing_to_do') {
                Logger::info($this->config, 'KAS request returned nothing_to_do; treating as success', array_merge($logContext, [
                    'kas_action' => $action,
                    'attempt' => $attempt,
                ]));

                return [
                    'Response' => [
                        'ReturnString' => 'TRUE',
                        'ReturnInfo' => 'nothing_to_do',
                        'KasFloodDelay' => 0.5,
                    ],
                ];
            }

            if (in_array($faultString, ['flood_protection', 'in_progress'], true) && $attempt < $maxAttempts) {
                sleep($attempt * 2);
                continue;
            }

            throw new UserVisibleException($this->mapKasFaultToUserMessage($faultString), 0, $fault);
        }
    }
}

private function tryResolveMailLogin(
    string $kasLogin,
    string $sessionToken,
    string $apiWsdl,
    string $identifier
): ?string {
    try {
        return $this->resolveMailLogin($kasLogin, $sessionToken, $apiWsdl, $identifier);
    } catch (UserVisibleException $e) {
        return null;
    }
}


private function waitForMailAccountReady(
    string $kasLogin,
    string $sessionToken,
    string $apiWsdl,
    string $mailAddress,
    int $maxAttempts = 8
): string {
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        sleep(2);

        $accounts = $this->getMailaccounts($kasLogin, $sessionToken, $apiWsdl);

        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }

            $mailLogin = (string)($account['mail_login'] ?? '');
            $addresses = strtolower((string)($account['mail_addresses'] ?? $account['mail_adresses'] ?? ''));
            $inProgress = strtoupper((string)($account['in_progress'] ?? ''));

            $addressList = array_map(
                static fn($value) => trim(strtolower($value)),
                explode(',', $addresses)
            );

            if (in_array(strtolower($mailAddress), $addressList, true) && $mailLogin !== '') {
                if ($inProgress === 'FALSE' || $inProgress === 'N' || $inProgress === '0' || $inProgress === '') {
                    Logger::info($this->config, 'KAS mail account is ready', [
                        'mail_address' => $mailAddress,
                        'mail_login' => $mailLogin,
                        'attempt' => $attempt,
                    ]);

                    return $mailLogin;
                }

                Logger::info($this->config, 'KAS mail account still in progress', [
                    'mail_address' => $mailAddress,
                    'mail_login' => $mailLogin,
                    'attempt' => $attempt,
                ]);
            }
        }
    }

    throw new UserVisibleException(
        'Das Mailkonto wurde angelegt, ist bei KAS aber noch nicht fertig verarbeitet. Bitte warte kurz und speichere den Benutzer danach erneut.'
    );
}

private function waitForKasFloodDelay(string $key): void
{
    $lastRequestAt = $this->kasLastRequestAt[$key] ?? null;
    $delaySeconds = $this->kasFloodDelays[$key] ?? 0.8;

    if ($lastRequestAt === null) {
        return;
    }

    $elapsed = microtime(true) - $lastRequestAt;
    $remaining = $delaySeconds - $elapsed;

    if ($remaining > 0) {
        usleep((int)ceil($remaining * 1_000_000));
    }
}

private function rememberKasFloodDelay(string $key, $result): void
{
    $this->kasLastRequestAt[$key] = microtime(true);

    if (is_object($result)) {
        $result = json_decode(json_encode($result), true) ?: [];
    }

    if (!is_array($result)) {
        return;
    }

    $delay = null;

    if (isset($result['Response']['KasFloodDelay'])) {
        $delay = (float)$result['Response']['KasFloodDelay'];
    } elseif (isset($result['KasFloodDelay'])) {
        $delay = (float)$result['KasFloodDelay'];
    }

    if ($delay !== null && $delay > 0) {
        // Kleine Sicherheitsmarge, weil Shared Hosting/Netzwerk-Timing ungenau sein kann.
        $this->kasFloodDelays[$key] = $delay + 0.25;
    }
}
public function deleteMailAccountByAddress(string $mailAddress, string $session2fa = ''): void
{
    $kasConfig = $this->config['kas'] ?? [];

    if (($kasConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('KAS-API ist in der Config nicht aktiviert.');
    }

    if (!class_exists(SoapClient::class)) {
        throw new RuntimeException('PHP SOAP Extension ist nicht verfügbar.');
    }

    $kasLogin = (string)($kasConfig['login'] ?? '');
    $kasPassword = (string)($kasConfig['password'] ?? '');
    $authWsdl = (string)($kasConfig['auth_wsdl'] ?? '');
    $apiWsdl = (string)($kasConfig['api_wsdl'] ?? '');

    if ($kasLogin === '' || $kasPassword === '' || $authWsdl === '' || $apiWsdl === '') {
        throw new RuntimeException('KAS-API-Konfiguration ist unvollständig.');
    }

    $token = $this->createSessionToken($kasLogin, $kasPassword, $authWsdl, $session2fa);

    $mailLogin = $this->resolveMailLogin(
        $kasLogin,
        $token,
        $apiWsdl,
        $mailAddress
    );

    $params = [
        'kas_login' => $kasLogin,
        'kas_auth_type' => 'session',
        'kas_auth_data' => $token,
        'kas_action' => 'delete_mailaccount',
        'KasRequestParams' => [
            'mail_login' => $mailLogin,
        ],
    ];

    $result = $this->kasApiRequest(
        $apiWsdl,
        $params,
        'KAS SOAP error while deleting mail account',
        [
            'mail_address' => $mailAddress,
            'mail_login' => $mailLogin,
        ]
    );

    Logger::info($this->config, 'KAS mail account deleted', [
        'mail_address' => $mailAddress,
        'mail_login' => $mailLogin,
        'result' => is_scalar($result) ? (string)$result : gettype($result),
    ]);
}









}