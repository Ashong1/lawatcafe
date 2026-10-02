# Test inventory

> Generated from a full PHPUnit run on 2026-10-02 (system v1.25.0.197; docs published in build 198): **1111 tests, 3821 assertions, 0 failures**, 158 test classes. Run `php artisan test` to reproduce (see [../TESTING.md](../TESTING.md)). Modules are grouped by class name, so a few classes may sit in a neighbouring module.

Use this for Chapter 3 (testing procedure) and Chapter 4 (functional testing results). Each test is an automated check that a requirement holds; method names read as sentences (e.g. `test_an_e_wallet_sale_needs_a_reference_number_and_records_it`).

## Summary by module

| Module | Test classes | Tests | Assertions | Passed |
|---|---|---|---|---|
| Point of sale (register, orders, shifts, payments) | 25 | 142 | 415 | 142/142 |
| Inventory (products, ingredients, suppliers, deliveries) | 16 | 90 | 252 | 90/90 |
| Network and captive portal | 46 | 414 | 1324 | 414/414 |
| AI agent (Barista AI) | 29 | 230 | 693 | 230/230 |
| Accounts, security and roles | 15 | 96 | 399 | 96/96 |
| User interface and accessibility | 16 | 93 | 633 | 93/93 |
| Other (versioning, configuration, utilities) | 11 | 46 | 105 | 46/46 |
| **Total** | **158** | **1111** | **3821** | **1111/1111** |

## Point of sale (register, orders, shifts, payments)

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `CaptivePortalUploadReceiptTest` | 2 | 4 | Passed |
| `EndOfDayControllerTest` | 3 | 11 | Passed |
| `EwalletPaymentTest` | 8 | 33 | Passed |
| `GetSalesSummaryToolTest` | 8 | 17 | Passed |
| `KdsDataEndpointTest` | 2 | 8 | Passed |
| `KitchenSlipTest` | 3 | 16 | Passed |
| `OrderHistoryControllerTest` | 5 | 10 | Passed |
| `OrderWaitReminderTest` | 6 | 26 | Passed |
| `PairingSuggestionServiceTest` | 5 | 5 | Passed |
| `PosCheckoutTest` | 8 | 28 | Passed |
| `PosFreeWifiUpsellTest` | 8 | 22 | Passed |
| `PosPairingSuggestionTest` | 9 | 14 | Passed |
| `PosReceiptBirGateTest` | 12 | 28 | Passed |
| `PosReceiptTest` | 2 | 4 | Passed |
| `PosRegisterPerformanceTest` | 4 | 18 | Passed |
| `PurchaseOrderControllerTest` | 4 | 9 | Passed |
| `SaleVoidRequestTest` | 7 | 22 | Passed |
| `SaleVoidTest` | 7 | 17 | Passed |
| `SalesControllerTest` | 2 | 5 | Passed |
| `ShiftAuditServiceTest` | 4 | 14 | Passed |
| `ShiftControllerTest` | 10 | 37 | Passed |
| `ShiftToolDescriptionScopeTest` | 2 | 4 | Passed |
| `SuperAdminRegisterAccessTest` | 8 | 25 | Passed |
| `SupplierAndShiftToolsTest` | 9 | 26 | Passed |
| `SupplierOrderServiceTest` | 4 | 12 | Passed |

## Inventory (products, ingredients, suppliers, deliveries)

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `CategoryAiSuggestionTest` | 4 | 9 | Passed |
| `CategoryDescriptionContextTest` | 4 | 14 | Passed |
| `CheckStockLevelsToolTest` | 6 | 14 | Passed |
| `IngredientControllerTest` | 5 | 22 | Passed |
| `IngredientDeliveryControllerTest` | 3 | 10 | Passed |
| `IngredientFormattedStockTest` | 6 | 11 | Passed |
| `IngredientServiceTest` | 5 | 10 | Passed |
| `ListSupplierPoDraftsToolTest` | 6 | 12 | Passed |
| `LowStockThresholdTest` | 7 | 12 | Passed |
| `ProductControllerTest` | 8 | 21 | Passed |
| `ProductImageTest` | 7 | 22 | Passed |
| `ProductStatusBadgeTest` | 4 | 10 | Passed |
| `StaffDeliveryReceivingTest` | 8 | 36 | Passed |
| `SuggestCategoryContentToolTest` | 6 | 15 | Passed |
| `SupplierControllerTest` | 6 | 14 | Passed |
| `WastageControllerTest` | 5 | 20 | Passed |

## Network and captive portal

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `AdaptiveBandwidthTest` | 26 | 62 | Passed |
| `AgentToolTierTest` | 2 | 33 | Passed |
| `AllowedAddressControllerTest` | 7 | 14 | Passed |
| `BlocklistControllerTest` | 6 | 19 | Passed |
| `BlocklistServiceTest` | 6 | 25 | Passed |
| `CaptivePortalActivationTest` | 22 | 78 | Passed |
| `CaptivePortalApiTest` | 10 | 21 | Passed |
| `CaptivePortalAuthenticateTest` | 14 | 27 | Passed |
| `CaptivePortalChatTest` | 7 | 24 | Passed |
| `CaptivePortalMenuTest` | 8 | 20 | Passed |
| `CaptivePortalNoScriptSignInTest` | 4 | 15 | Passed |
| `CaptivePortalStatusPageTest` | 13 | 45 | Passed |
| `CaptivePortalVerifyPaymentTest` | 2 | 4 | Passed |
| `CheckMySessionToolTest` | 6 | 13 | Passed |
| `DhcpPoolInfrastructureGuardTest` | 8 | 27 | Passed |
| `EnforceSessionLimitsTest` | 13 | 43 | Passed |
| `FairUseCapTest` | 18 | 41 | Passed |
| `GetActiveSessionsToolTest` | 2 | 8 | Passed |
| `GhostDeviceDetectionServiceTest` | 6 | 12 | Passed |
| `IdleSessionTimeoutTest` | 3 | 8 | Passed |
| `KeepaliveGuestSessionsTest` | 4 | 10 | Passed |
| `LookupVoucherToolTest` | 5 | 10 | Passed |
| `NetworkDeviceActionsTest` | 7 | 33 | Passed |
| `NetworkHealthTest` | 10 | 36 | Passed |
| `NetworkToolsTest` | 12 | 27 | Passed |
| `OfflineModeTest` | 7 | 22 | Passed |
| `OpnSenseServiceTest` | 24 | 60 | Passed |
| `PiholeServiceTest` | 13 | 26 | Passed |
| `PortalBackgroundCompositingTest` | 5 | 33 | Passed |
| `PortalGuestImprovementsTest` | 8 | 28 | Passed |
| `PortalMobileLayoutTest` | 11 | 42 | Passed |
| `PortalQrCodeTest` | 8 | 69 | Passed |
| `PortalRoundTwoTest` | 9 | 42 | Passed |
| `SettingInfrastructureIpsTest` | 3 | 6 | Passed |
| `SiteBlockingControllerTest` | 12 | 38 | Passed |
| `SiteBlockingToolsTest` | 8 | 28 | Passed |
| `StaffCanViewAndKickSessionsTest` | 3 | 8 | Passed |
| `TierMembershipReconcileTest` | 7 | 14 | Passed |
| `TrafficPlanSpeedsTest` | 10 | 38 | Passed |
| `TrafficShapingServiceTest` | 12 | 46 | Passed |
| `TrustedDevicesTest` | 9 | 36 | Passed |
| `VoucherControllerTest` | 15 | 41 | Passed |
| `VoucherDestructiveActionsTest` | 5 | 22 | Passed |
| `VoucherServiceTest` | 4 | 23 | Passed |
| `VoucherSessionsTest` | 6 | 27 | Passed |
| `WatchAdultSitesTest` | 14 | 20 | Passed |

## AI agent (Barista AI)

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `AIServiceGuestStoreInfoTest` | 9 | 11 | Passed |
| `AIServicePromptCachingTest` | 2 | 9 | Passed |
| `AIServiceProviderFallbackTest` | 3 | 4 | Passed |
| `AIServiceStaffPromptTest` | 5 | 6 | Passed |
| `AIServiceStreamingCascadeTest` | 4 | 7 | Passed |
| `AgentActivityPageTest` | 6 | 59 | Passed |
| `AgentChatCloseAnimationTest` | 4 | 12 | Passed |
| `AgentChatHistoryUiTest` | 3 | 6 | Passed |
| `AiActionConfirmFlowTest` | 5 | 16 | Passed |
| `AiAnalysisControllerTest` | 2 | 10 | Passed |
| `AiConversationHistoryTest` | 7 | 18 | Passed |
| `AiDailyQuotaTest` | 8 | 23 | Passed |
| `AiLearningLoopTest` | 32 | 85 | Passed |
| `AiMenuContextFreshnessTest` | 6 | 14 | Passed |
| `AiProviderStatusTest` | 22 | 79 | Passed |
| `BaristaForecastServiceTest` | 5 | 20 | Passed |
| `CapabilityGapLearningTest` | 11 | 32 | Passed |
| `ChatHistoryNullContentTest` | 3 | 3 | Passed |
| `ChatHistorySlidingWindowTest` | 4 | 6 | Passed |
| `ChatImageAttachmentTest` | 10 | 39 | Passed |
| `ChatStreamTruncationTest` | 11 | 34 | Passed |
| `GetAnomalySignalsToolTest` | 4 | 14 | Passed |
| `GuestChatHardeningTest` | 11 | 23 | Passed |
| `RunAgentAnalysisTest` | 3 | 13 | Passed |
| `SuperAdminSystemToolsTest` | 10 | 28 | Passed |
| `ToolCallOrchestratorReasoningDepthTest` | 2 | 5 | Passed |
| `ToolCallOrchestratorTest` | 14 | 43 | Passed |
| `ToolRegistryIsolationTest` | 8 | 40 | Passed |
| `WriteToolsGlueTest` | 16 | 34 | Passed |

## Accounts, security and roles

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `AccountControllerTest` | 8 | 23 | Passed |
| `AccountInviteFlowTest` | 14 | 67 | Passed |
| `AdminLayoutMobileShellTest` | 10 | 34 | Passed |
| `AdminSettingSubflowsTest` | 11 | 32 | Passed |
| `AuthenticationTest` | 4 | 8 | Passed |
| `DashboardRoleSplitTest` | 10 | 130 | Passed |
| `EmailVerificationTest` | 3 | 6 | Passed |
| `PasswordConfirmationTest` | 3 | 4 | Passed |
| `PasswordResetTest` | 5 | 13 | Passed |
| `PasswordUpdateTest` | 2 | 8 | Passed |
| `PermissionResolverTest` | 7 | 13 | Passed |
| `ProfileTest` | 5 | 21 | Passed |
| `RoleLabelDisplayTest` | 5 | 17 | Passed |
| `RoleMiddlewareTest` | 6 | 8 | Passed |
| `StaffControllerTest` | 3 | 15 | Passed |

## User interface and accessibility

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `AndroidAppTest` | 5 | 16 | Passed |
| `DashboardLiveDataTest` | 4 | 31 | Passed |
| `HeaderBadgeSeedingTest` | 2 | 4 | Passed |
| `HeaderDropdownStackingTest` | 1 | 4 | Passed |
| `HeaderDropdownXCloakTest` | 2 | 2 | Passed |
| `MobileLayoutTest` | 13 | 51 | Passed |
| `NotificationControllerTest` | 7 | 20 | Passed |
| `NotificationDeletionTest` | 10 | 28 | Passed |
| `SidebarMenuStateTest` | 9 | 22 | Passed |
| `UiReadabilityFloorTest` | 3 | 286 | Passed |
| `UiUxAccessibilitySweepTest` | 10 | 55 | Passed |
| `UiUxColorContrastTest` | 4 | 9 | Passed |
| `UiUxFieldValidationErrorsTest` | 6 | 48 | Passed |
| `UiUxPhase1RegressionTest` | 7 | 30 | Passed |
| `UiUxPhase2RegressionTest` | 5 | 12 | Passed |
| `ViewSanitizationFixesTest` | 5 | 15 | Passed |

## Other (versioning, configuration, utilities)

| Test class | Tests | Assertions | Result |
|---|---|---|---|
| `ActiveGuestCountAgreementTest` | 5 | 9 | Passed |
| `AnalyticsControllerTest` | 4 | 10 | Passed |
| `AuditLoggerTest` | 4 | 8 | Passed |
| `DatabaseSeederTest` | 2 | 6 | Passed |
| `ExampleTest` | 2 | 3 | Passed |
| `FreeModelDiscoveryTest` | 4 | 5 | Passed |
| `GuestFreeModelsOnlyTest` | 4 | 8 | Passed |
| `SettingCacheTest` | 3 | 5 | Passed |
| `SkeletonLoaderTest` | 7 | 25 | Passed |
| `StaticIpControllerTest` | 6 | 18 | Passed |
| `VersioningTest` | 5 | 8 | Passed |
