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

    'social_email_missing' => 'That provider did not share an email address, so we cannot match it to an account.',
    'social_link_required' => 'An account already uses that address. Sign in with your password, then connect the provider from your security settings.',
    'social_session_expired' => 'That sign-in took too long. Start again.',
    'social_failed' => 'Signing in with that provider did not work. Try again.',

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

    'email_taken' => 'That address now belongs to another account.',
    'email_change_requested' => 'Check the new address for a confirmation link.',
    'email_change_subject' => 'Confirm your new address for :app',
    'email_change_line' => 'Open the link below to move your :app account to this address.',
    'email_change_action' => 'Confirm address',
    'email_change_expires' => 'The link expires on :date.',
    'email_change_ignore' => 'If you did not ask for this, ignore this mail. Nothing has changed.',
    'email_change_notice_subject' => 'An address change was requested on your :app account',
    'email_change_notice_line' => 'Someone asked to move your account to :email. It will not move until that address confirms.',
    'email_change_notice_warning' => 'If this was not you, change your password now and sign out the other devices.',
    'email_changed_subject' => 'The address on your :app account has changed',
    'email_changed_line' => 'Your account now uses :email. This address no longer signs in.',

    'unknown_device' => 'Unknown device',
    'session_revoked' => 'This device was signed out from another session.',
    'session_is_current' => 'You cannot sign out the device you are using.',

];
