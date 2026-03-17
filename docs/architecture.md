Beopp is a contest voting platform.

Users authenticate using AWS Cognito.
Users vote on contest entries.

Votes can be:
- free
- paid via Stripe

Paid votes are processed through Stripe Checkout and finalized by Stripe webhooks.