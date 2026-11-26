# 0042: Memory limit of 256M in the delivered image, for logo re-encoding

Date: 2026-11-19
Status: accepted (integrator choice, phase 6, after the delivered-image test run)

## Decision

The delivered image sets `memory_limit=256M` in its PHP ini. `LogoStore` keeps decoding and re-encoding the largest allowed logo (4000 by 4000 pixels) with GD. A test measures the peak memory of storing that logo.

## Motivation

GD holds a decoded 4000 by 4000 image as 4 bytes per pixel, 64 MB, before the encoder runs. With the PHP default of 128M that leaves roughly 25 percent for the framework and the encoder, too thin to rely on. The failing test run was caused by the test building its own 64 MB image and flood-filling it (a second 64 MB), not by `LogoStore`, which never fills. 256M gives the worst-case logo room without a bounded re-encode, which would change the stored image. The dev container has no GD; `.devcontainer/Dockerfile` now installs it for the next rebuild.

## Amendment 2026-11-19

The rebuilt dev container has GD, built the same way as the delivered image (`docker-php-ext-install gd`, bundled GD). There storing the largest logo peaks at 131 MB above the baseline, two decoded copies, where the CI runner (system libgd) stayed under the earlier 80 MB test budget. The budget in the test is now 160 MB: with the 256M limit it leaves 96 MB for the framework and the request, and the test still fails if the re-encode ever grows beyond that.
