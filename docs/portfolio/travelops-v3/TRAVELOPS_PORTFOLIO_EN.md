---
locale: en
translationKey: travelops
title: TravelOps
description: An independent ticketing demo connecting online checkout, store sales, venue redemption and operations through shared inventory, recoverable issuance and reconciliation.
category: TICKET FULFILLMENT & OPERATIONS
tags: [PHP, Laravel, Livewire, Filament, MySQL, Redis, Docker]
highlights: ['Shared online and POS inventory', 'Recoverable asynchronous issuance', 'Refund coordination and reconciliation']
status: Implemented · isolated showcase
visibility: private
featured: false
order: 3
sourceCommit: 11c0e7ae5fa828a69438f807be220c4f37be41c9
reviewedAt: '2026-11-12'
cover: ../../../assets/travelops/travelops-catalog-desktop.png
coverAlt: Actual TravelOps synthetic catalog showing ticket names, venue counts and prices in New Taiwan dollars
---

# TravelOps: following a ticket through fulfillment

TravelOps is an independently developed simulation of travel ticket fulfillment and operations. Customers, stores, venues and operators share one transaction model, including the awkward interval when payment is confirmed but tickets are still being issued. All venues, accounts and transactions are synthetic. Payment, suppliers, refunds and notifications use local Mock implementations.

## Problem, solution and observable outcome

Online checkout and store sales must not sell the same stock twice. Payment events can arrive more than once, and suppliers can time out. Separately updating refund approval and venue redemption can also let a refunded entitlement be used.

MySQL owns inventory and order state, with row locks coordinating the channels. Payment confirmation commits an issuance Outbox row in the same transaction; Redis Queue and workers deliver the work. After a lost response, a worker queries the separate local Mock ledger before replaying a request. Refund approval and redemption acquire the same order lock. Operations compares internal records with independently persisted Mock statements.

The result is an inspectable synthetic transaction journey: customers can follow payment and issuance, then inspect remaining venue entitlements. POS estimates two single-venue tickets at NT$640 and the server confirms the sale. Reconciliation exposes differences and preserves the operator's explanation. These are demonstrated system behaviors, with no invented revenue, time savings or customer outcomes.

## Four working perspectives

- **Customer:** browse single-venue and bundle tickets, check how the 14-day validity starts, inspect available stock and estimated totals, place an order, simulate payment, follow issuance and request a full refund.
- **Store:** select products, quantities and cash or a simulated card at an authorized store, confirm the live summary and review sales. Supervisors can also view a Taiwan-business-day closing summary.
- **Venue:** redeem an entitlement only for an assigned venue and receive success, duplicate-use or paused-redemption feedback. The demonstrated workflow uses text-code entry; physical camera scanning was not verified.
- **Operations:** investigate issuance and delivery failures, approve refunds, compare internal and Mock statements, record dispositions and filter reports by date, channel, store or supplier.

V3 keeps the existing Blade, Livewire and Filament interaction model while clarifying order progress, refund pauses, POS confirmation and side-by-side reconciliation. Mobile screenshots come from an actual 390-pixel viewport.

## Architecture and stack

The modular monolith uses PHP 8.4, Laravel 13.40.0, Livewire 4.4.8, Filament 5.11.2, Blade and Tailwind CSS 4. MySQL 8.4/InnoDB stores transactions, stock, tickets, refunds, Outbox work, the Webhook Inbox, audit records and separate Mock ledgers. Redis 7.4 carries disposable jobs that durable rows can rebuild. Docker Compose runs the app, worker, scheduler, MySQL and Redis separately; GitHub Actions supplies quality and integration checks. Package versions come from the source lockfiles; the media validation report records the actual container versions.

The system overview introduces the roles and data paths. The transaction diagram then expands the locking and recovery boundaries. Neither diagram claims a cloud deployment or a connection to a real payment or supplier service.

## Five engineering decisions

1. **Shared stock locks.** Both channels use the same Checkout service. Product and inventory rows are locked before creating the order snapshot and reservation. Short transactions provide consistent sales rules; throughput has not been measured.
2. **Idempotent callbacks.** Signatures, references, amounts and currency are checked. A unique event and body hash distinguish replay from conflicting content. Supplier and refund callbacks also validate a timestamp window; that claim does not apply indiscriminately to every payment callback.
3. **Transactional Outbox.** Payment and issuance work commit together in MySQL. The scheduler rediscovers due work while Redis handles delivery. Stable request keys, leases and query-before-replay support recovery after lost responses. Supplier calls run outside order locks, followed by a new confirmation transaction. POS deliberately keeps its local Mock capture inside the outer transaction; this is not a design claim about real network payment calls.
4. **Refund and redemption coordination.** Approved, unresolved refunds pause redemption. Confirmed settlement revokes entitlements, returns sold stock and schedules supplier cancellation. Unconfirmed cancellation remains visible to operators. Only full-order refunds are supported.
5. **Reconciliation and reporting semantics.** Original transactions, Mock statements and human dispositions remain separate. Reconciliation reruns converge findings; marking a finding resolved does not rewrite amounts. The UI displays New Taiwan dollars, while CSV exports use integer cents. Business dates follow Taiwan time.

## Tests and evidence boundaries

The source V3 acceptance record dated 2026-11-10 reports 34 PHPUnit tests and 415 assertions, Windows Docker validation, Chrome journeys and checks at 1280, 768 and 390 pixels. Its V3 commit is `b1f2b1ef36479dbaa97c82cd48a5513c7d5d7c31`, subsequently merged into this case study's `sourceCommit`. A read-only check on 2026-11-12 confirmed that the associated PR CI succeeded. Those historical records are distinct from this media run.

The media validation report lists this run's executed checks, eight screenshots, video metadata and visual review. Browser capture does not establish production readiness, load capacity or comprehensive accessibility compliance.

## Suggested media sequence and alternatives

| Order | Asset | Alt text / caption |
| --- | --- | --- |
| 1 | Catalog / cover | Actual synthetic catalog: ticket names, venue counts and New Taiwan dollar prices. |
| 2 | Demo video and poster | Actual customer, store and operations interactions, using synthetic data and local Mock integrations. |
| 3 | Mobile product | A 390-pixel product view with validity, available stock and an estimated total. |
| 4 | Order and entitlements | Payment, issuance and per-venue entitlement states; complete identifiers and redemption credentials are redacted. |
| 5 | Desktop and mobile POS | Product, unit price, two tickets, simulated card and the NT$640 estimate. |
| 6 | System overview | Four roles enter authorized Laravel interfaces backed by MySQL; a scheduler, Redis and workers coordinate local Mock fulfillment. |
| 7 | Critical transaction flow | Shared stock, payment Outbox, calls outside locks and confirmation transactions, including refund/redemption coordination. |
| 8 | Reconciliation and reports | Differences between synthetic ledgers, disposition controls and reporting filters; these are not real business results. |

Video preview: “Follow one ticket through payment, issuance, store sales and operations.” The poster uses an actual UI frame with a synthetic-demo disclosure. Let Astro manage image sizes; on mobile, provide these text summaries instead of relying on a diagram reduced below readable size.

## Limits and collaboration

This independent portfolio project has no production operation, real customers, actual payments, live suppliers, external Email/LINE delivery or verified commercial results. This run did not publish a deployment. Comprehensive cross-browser, screen-reader, physical-device, camera and production-load validation remains outside the evidence. Codex assisted with media preparation and explanations grounded in the existing implementation and tests; this case study does not infer human versus AI ownership of every original design. The owner should review the assets and trademark use before publication.
