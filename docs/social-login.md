# Social login

Sign in with an identity provider, and connect one to an account that already
exists. Off by default: a provider appears only once its credentials are set,
so an unconfigured provider has no button rather than a button that fails on
click.

## Wiring one up

**1. Register an OAuth client with the provider.** The callback URL is

```
https://your-app.example/auth/social/<provider>/callback
```

and, if you want users to be able to connect a provider from their security
settings,

```
https://your-app.example/auth/social/<provider>/link/callback
```

**2. Put the credentials in `.env`.**

```dotenv
GOOGLE_CLIENT_ID=…
GOOGLE_CLIENT_SECRET=…
```

`config/services.php` already reads those for Google, GitHub, GitLab,
Bitbucket and LinkedIn.

**3. That is it.** `App\Support\SocialProviders` derives the offered list from
whether both the id and the secret are filled, `RuntimeConfigPayload` ships it
to the client, and `SocialSignIn` renders a button per provider on the sign-in
and registration pages. The account's security page grows a "Connected
accounts" section at the same time.

Set `APP_FEATURE_SOCIAL=false` to turn the whole thing off without removing
credentials.

## Adding a provider

`config/social.php` lists what may be offered — label, Iconify icon name and
scopes:

```php
'discord' => [
    'label' => 'Discord',
    'icon' => 'logos:discord-icon',
    'scopes' => ['identify', 'email'],
],
```

Add the matching `services.discord` block with `client_id` and
`client_secret`. Socialite ships drivers for its own set; anything else needs
[Socialite Providers](https://socialiteproviders.com) registered in a service
provider first.

## What the callback does

The whole security model is in `resolveUser()`:

1. **A known identity signs in.** The `(provider, external_id)` pair is unique,
   so a provider account can only ever reach the local account it is linked
   to.
2. **An unknown identity with a matching address** is adopted _only_ when both
   sides verified that address: the local account has `email_verified_at`, and
   the provider says `email_verified` in its payload. Otherwise the attempt is
   refused with "sign in with your password, then connect the provider".

   Adopting on a bare email match is a pre-registration hijack: an attacker
   registers under the victim's address and waits for them to arrive through
   the provider, landing them in an account the attacker still holds the
   password to.

3. **An unknown identity with an unknown address** registers, subject to the
   same first-account latch as `RegisterController`. Once an installation has
   an account, the provider redirect is not an open registration endpoint.
4. **Two-factor still applies.** A confirmed second factor stops the sign-in
   at `/login?social_2fa=1`; the session holds the user id and nothing else
   until `POST /auth/social/2fa` verifies a code. A provider that vouches for
   an address does not stand in for the second factor.

Failures never say which check refused. The difference between "no such
account" and "that account exists but is unverified" is exactly what someone
probing addresses wants to learn.

Accounts created through a provider get a random password nobody knows,
including their owner. Signing in with a password means going through the
reset flow first, which is also the escape hatch when a provider becomes
unreachable — which is why disconnecting the last provider is allowed rather
than refused.

## Connecting from an existing session

`GET /auth/social/{provider}/link` is authenticated and returns to
`/account/security`. If the provider identity already belongs to another local
account the redirect carries `?social=conflict` and nothing is written.
Disconnecting is a step-up action: it needs a fresh password confirmation, and
it writes a `social_unlinked` entry into the account's security trail.

## What is stored

`user_social_links`: the provider, the external id, and the nickname and
address the provider reported, for the settings page to show. No access or
refresh token — sign-in is all this is for, and a token the app never calls
with is a credential to lose rather than a feature.

## Invitations

An invited user accepts the invitation first, which creates their account, and
can connect a provider afterwards from their security settings. The provider
redirect deliberately carries no invite token.
