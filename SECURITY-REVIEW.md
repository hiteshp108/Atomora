# Atomora Security Review

**Review date:** 2026-10-23  
**Scope:** Atomora plugin source at the repository root and GitHub repository access settings.

## Findings

### MEDIUM — Private or draft WooCommerce product details may be exposed

**Confidence:** 8/10  
**Affected code:**

- `includes/widgets/class-product-card.php:672-682, 780, 797, 873-877`
- Related product lookups: `includes/widgets/class-review-context.php:90-92` and `includes/widgets/trait-review-part.php:137-139`

When a product is selected manually by ID, the frontend rendering path does not check that the product is publicly viewable before rendering its details. An editor who is allowed to publish a public Elementor page could select a private or draft product and expose information such as its name or price on that page.

**Recommendation:** Check product publication status and public visibility before rendering manually selected products. Apply the check consistently to the product card and shared review/product lookup paths.

## GitHub repository access

The repository is public, so anyone can view, clone, or fork it and submit issues or pull requests. Public visibility alone does not grant permission to push changes to the original repository.

At the time of review, GitHub listed only the repository owner account (`hiteshp108`) as a collaborator, with administrator access. The `main` branch had no branch protection or repository ruleset configured. This means the current owner can push directly to `main`; any future collaborator granted write access could also push directly.

**Recommendation:** Enable a ruleset for `main` that requires changes to go through pull requests and blocks force pushes and branch deletion. Keep account two-factor authentication enabled.

## Scope and limitations

This report records a source review and a snapshot of repository settings; it is not a guarantee that all vulnerabilities have been found. No live WordPress site, deployed configuration, or runtime behavior was tested. Repository settings may change after the review date.
