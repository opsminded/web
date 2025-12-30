<?php
/**
 * Root entry point - Redirects to production environment
 *
 * This file provides a convenient access point at the root of the project
 * that automatically redirects to the production public folder.
 *
 * Environments:
 * - Development: /public/dev/
 * - QA: /public/qa/
 * - Production: /public/prod/
 */

// Redirect to production environment
header('Location: /public/prod/');
exit;
