<?php

declare(strict_types=1);

return [

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'unauthenticated' => 'You are not signed in.',
    'login_successful' => 'Signed in.',
    'registration_closed' => 'Registration is by invitation only.',
    'email_not_verified' => 'Please verify your email address first.',
    'already_verified' => 'This address is already verified.',
    'verification_sent' => 'A new verification link has been sent.',
    'password_updated' => 'Your password has been changed.',
    'password_confirmed' => 'Password confirmed.',
    'password_confirmation_required' => 'Please confirm your password to continue.',
    'invalid_password' => 'That password is not correct.',
    'too_many_verification_attempts' => 'Too many attempts. Try again in :seconds seconds.',

    'totp_required' => 'Enter the code from your authenticator app.',
    'invalid_totp_code' => 'That code is not valid.',
    'totp_already_enabled' => 'Two-factor authentication is already enabled.',
    'totp_not_enabled' => 'Two-factor authentication is not enabled.',
    'totp_setup_expired' => 'The setup has expired. Start again.',
    'totp_disabled' => 'Two-factor authentication has been disabled.',

    'not_impersonating' => 'This session is not impersonating anyone.',
    'not_while_impersonating' => 'Not available while impersonating another user.',
    'last_owner' => 'The last owner cannot be removed or demoted.',

    'invite_invalid' => 'This invitation is no longer valid.',
    'invite_email_mismatch' => 'This invitation was sent to a different email address.',
    'invite_requires_account' => 'Create an account to accept this invitation.',
    'invite_subject' => 'You have been invited to :app',
    'invite_line' => 'You have been invited to join :app.',
    'invite_line_by' => ':name has invited you to join :app.',
    'invite_action' => 'Accept invitation',
    'invite_expires' => 'This invitation expires on :date.',

];
