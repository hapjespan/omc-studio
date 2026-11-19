# Revert of integration/d2 (PR 382)

Pull request 382 (merge commit a9d6363) merged integration/d2 into release. The product owner decided that release holds delivery 1 only, so the merge was reverted with `git revert -m 1 a9d6363`.

Revert commit: 79c892aa72d6d771c6083d97342e5bee8ad10cca

Delivery 2 comes back later by first reverting this revert commit and then merging integration/d2 again. Merging integration/d2 without that step would leave out everything the earlier merge brought, because git treats it as already merged.
