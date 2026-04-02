<?php

use App\Http\Controllers\Api\Activities\BusinessDealHistoryController;
use App\Http\Controllers\Api\Activities\P2pMeetingHistoryController;
use App\Http\Controllers\Api\Activities\ReferralHistoryController;
use App\Http\Controllers\Api\Activities\RequirementController as ActivitiesRequirementController;
use App\Http\Controllers\Api\Activities\RequirementHistoryController;
use App\Http\Controllers\Api\Activities\TestimonialHistoryController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdsController;
use App\Http\Controllers\Api\Admin\CircleJoinRequestAdminController;
use App\Http\Controllers\Api\AdminActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessDealController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatTypingController;
use App\Http\Controllers\Api\CircleChatController;
use App\Http\Controllers\Api\CircleController;
use App\Http\Controllers\Api\CircularController;
use App\Http\Controllers\Api\CircleJoinRequestController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MembershipSummaryController;
use App\Http\Controllers\Api\MessageDeletionController;
use App\Http\Controllers\Api\MemberWithCircleController;
use App\Http\Controllers\Api\MyCircleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\P2pMeetingController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostSaveController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\UserContactController;
use App\Http\Controllers\Api\V1\Billing\BillingCheckoutController;
use App\Http\Controllers\Api\V1\Billing\CircleSubscriptionController;
use App\Http\Controllers\Api\V1\Billing\ZohoBillingWebhookController;
use App\Http\Controllers\Api\V1\Circles\CircleMemberController as V1CircleMemberController;
use App\Http\Controllers\Api\V1\CoinClaimController;
use App\Http\Controllers\Api\V1\CoinHistoryController;
use App\Http\Controllers\Api\V1\CoinsController;
use App\Http\Controllers\Api\V1\CollaborationPostController;
use App\Http\Controllers\Api\V1\CollaborationTypeController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\Admin\AppVersionController as AdminAppVersionController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\Connections\MyConnectionsController;
use App\Http\Controllers\Api\V1\EventGalleryApiController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\Forms\LeaderInterestController;
use App\Http\Controllers\Api\V1\Forms\BecomeMentorController;
use App\Http\Controllers\Api\V1\Forms\PeerRecommendationController;
use App\Http\Controllers\Api\V1\Forms\VisitorRegistrationController;
use App\Http\Controllers\Api\V1\Forms\WebsiteFormsController;
use App\Http\Controllers\Api\V1\IndustryController;
use App\Http\Controllers\Api\V1\MembershipPlanController;
use App\Http\Controllers\Api\V1\P2PMeetingRequestController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PostReportController;
use App\Http\Controllers\Api\V1\PostReportReasonsController;
use App\Http\Controllers\Api\V1\Profile\MyPostsController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\RazorpayWebhookController;
use App\Http\Controllers\Api\V1\RequirementController as V1RequirementController;
use App\Http\Controllers\Api\V1\RequirementInterestController;
use App\Http\Controllers\Api\V1\TimelineRequirementController;
use App\Http\Controllers\Api\V1\Zoho\ZohoDebugController;
use App\Http\Controllers\Api\V1\Zoho\ZohoPlansController;
use App\Http\Controllers\Api\V1\Zoho\ZohoWebhookController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('request-otp', [AuthController::class, 'requestOtp']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::get('/posts/report-reasons', [PostReportReasonsController::class, 'index']);
    Route::get('/app/version', [AppVersionController::class, 'show']);
    Route::get('/referrals/validate/{code}', [ReferralController::class, 'validateCode']);

    Route::get('/industries/tree', [IndustryController::class, 'tree']);
    Route::get('/collaboration-types', [CollaborationTypeController::class, 'index']);

    Route::post('/contacts/sync', [UserContactController::class, 'syncContacts']);
    Route::get('/contacts', [UserContactController::class, 'getContacts']);
    Route::get('/members-with-circles', [MemberWithCircleController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/membership-summary', [MembershipSummaryController::class, 'show']);

        Route::get('/my-circles', [MyCircleController::class, 'index']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        // Members & connections
        Route::get('members/names', [MemberController::class, 'names']);

        Route::get('/members/profile/{slug}', [MemberController::class, 'publicProfileBySlug']);
        Route::get('/members/public/{slug}', [MemberController::class, 'publicProfileBySlug']);

        Route::apiResource('members', MemberController::class)
            ->only(['index', 'show']);

        Route::post('/members/{id}/connections', [MemberController::class, 'sendConnectionRequest']);
        Route::post('/members/{id}/connections/accept', [MemberController::class, 'acceptConnection']);
        Route::delete('/members/{id}/connections', [MemberController::class, 'deleteConnection']);
        Route::get('/connections', [MyConnectionsController::class, 'index']);
        Route::get('/connections/sent', [MyConnectionsController::class, 'sent']);
        Route::delete('/connections/sent/{addresseeId}', [MyConnectionsController::class, 'cancelSent']);

        Route::get('/me/connections', [MemberController::class, 'myConnections']);
        Route::get('/me/connection-requests', [MemberController::class, 'myConnectionRequests']);

        // Follow system
        Route::post('users/{user}/follow', [FollowController::class, 'requestFollow'])->whereUuid('user');
        Route::delete('users/{user}/unfollow', [FollowController::class, 'unfollow'])->whereUuid('user');
        Route::get('users/{user}/follow-status', [FollowController::class, 'status'])->whereUuid('user');

        Route::get('me/follow-requests', [FollowController::class, 'incomingRequests']);
        Route::get('me/following', [FollowController::class, 'myFollowing']);
        Route::get('me/followers', [FollowController::class, 'myFollowers']);

        Route::post('follows/{follow}/accept', [FollowController::class, 'accept'])->whereUuid('follow');
        Route::post('follows/{follow}/reject', [FollowController::class, 'reject'])->whereUuid('follow');
        Route::delete('follows/{follow}/cancel', [FollowController::class, 'cancel'])->whereUuid('follow');

        // Collaborations
        Route::post('/collaborations', [CollaborationPostController::class, 'store']);

        // Circles
        Route::get('/circles', [CircleController::class, 'index']);
        Route::get('/circles/{id}', [CircleController::class, 'show']);
        Route::post('/circles', [CircleController::class, 'store']);
        Route::put('/circles/{id}', [CircleController::class, 'update']);
        Route::patch('/circles/{id}', [CircleController::class, 'update']);
        Route::post('/circles/{id}/join', [CircleController::class, 'join']);
        Route::get('/my/circles', [CircleController::class, 'myCircles']);
        Route::get('/circles/{circle}/members', [V1CircleMemberController::class, 'index']);
        Route::put('/circles/{circleId}/members/{memberId}', [CircleController::class, 'updateMember']);
        Route::patch('/circles/{circleId}/members/{memberId}', [CircleController::class, 'updateMember']);

        // Circle Join Requests
        Route::post('/circle-join-requests', [CircleJoinRequestController::class, 'store']);
        Route::get('/circle-join-requests/my', [CircleJoinRequestController::class, 'myRequests']);
        Route::get('/circle-join-requests/{id}', [CircleJoinRequestController::class, 'show'])->whereUuid('id');
        Route::delete('/circle-join-requests/{id}', [CircleJoinRequestController::class, 'cancel'])->whereUuid('id');

        Route::prefix('admin')->group(function () {
            Route::post('/app/version', [AdminAppVersionController::class, 'upsert']);
            Route::get('/circle-join-requests', [CircleJoinRequestAdminController::class, 'index']);
            Route::get('/circle-join-requests/{id}', [CircleJoinRequestAdminController::class, 'show'])->whereUuid('id');
            Route::post('/circle-join-requests/{id}/approve-cd', [CircleJoinRequestAdminController::class, 'approveCd'])->whereUuid('id');
            Route::post('/circle-join-requests/{id}/reject-cd', [CircleJoinRequestAdminController::class, 'rejectCd'])->whereUuid('id');
            Route::post('/circle-join-requests/{id}/approve-id', [CircleJoinRequestAdminController::class, 'approveId'])->whereUuid('id');
            Route::post('/circle-join-requests/{id}/reject-id', [CircleJoinRequestAdminController::class, 'rejectId'])->whereUuid('id');
        });

        // Circle Chat
        Route::get('/circles/{circle}/chat/messages', [CircleChatController::class, 'index']);
        Route::post('/circles/{circle}/chat/messages', [CircleChatController::class, 'store']);
        Route::post('/circles/{circle}/chat/messages/read', [CircleChatController::class, 'markRead']);
        Route::get('/circles/{circle}/chat/messages/{message}/reads', [CircleChatController::class, 'readDetails']);
        Route::post('/circles/{circle}/chat/messages/{message}/delete-for-me', [CircleChatController::class, 'deleteForMe']);
        Route::delete('/circles/{circle}/chat/messages/{message}', [CircleChatController::class, 'destroy']);

        // Posts & feed
        Route::post('/posts/{post}/report', [PostReportController::class, 'store']);
        Route::get('/posts/feed', [PostController::class, 'feed']);
        Route::get('/ads', [AdController::class, 'index']);
        Route::get('/ads/timeline', [AdController::class, 'timeline']);
        Route::get('/ads/{id}', [AdController::class, 'show']);
        Route::get('/posts/saved', [PostSaveController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{id}', [PostController::class, 'show']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);

        Route::post('/posts/{id}/like', [PostController::class, 'like']);
        Route::delete('/posts/{id}/like', [PostController::class, 'unlike']);
        Route::post('/posts/{post}/save', [PostSaveController::class, 'toggle']);

        Route::post('/posts/{id}/comments', [PostController::class, 'storeComment']);
        Route::get('/posts/{id}/comments', [PostController::class, 'listComments']);
        Route::get('/profile/posts', [MyPostsController::class, 'index']);
        Route::get('/posts/{post}/likes', [MyPostsController::class, 'likes']);

        // Events
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events/{id}/rsvp', [EventController::class, 'rsvp']);
        Route::post('/events/{id}/checkin', [EventController::class, 'checkin']);

        // User Activities & Coins
        Route::post('/activities', [ActivityController::class, 'store']);
        Route::get('/activities/my', [ActivityController::class, 'myActivities']);
        Route::get('/activities/my/coins-summary', [ActivityController::class, 'myCoinsSummary']);
        Route::get('/activities/my/coins-ledger', [ActivityController::class, 'myCoinsLedger']);
        Route::get('/me/coins', [CoinsController::class, 'balance']);
        Route::get('/me/coins/ledger', [CoinsController::class, 'ledger']);
        Route::get('/coins/history', [CoinHistoryController::class, 'index']);

        Route::prefix('activities')->group(function () {
            Route::get('p2p-meetings', [P2pMeetingHistoryController::class, 'index']);
            Route::post('p2p-meetings', [P2pMeetingController::class, 'store']);
            Route::get('p2p-meetings/{id}', [P2pMeetingController::class, 'show']);

            Route::get('requirements', [RequirementHistoryController::class, 'index']);
            Route::post('requirements', [ActivitiesRequirementController::class, 'store']);
            Route::get('requirements/{id}', [ActivitiesRequirementController::class, 'show']);

            Route::get('referrals', [ReferralHistoryController::class, 'index']);
            Route::post('referrals', [ReferralController::class, 'store']);
            Route::get('referrals/{id}', [ReferralController::class, 'show']);

            Route::get('business-deals', [BusinessDealHistoryController::class, 'index']);
            Route::post('business-deals', [BusinessDealController::class, 'store']);
            Route::get('business-deals/{id}', [BusinessDealHistoryController::class, 'show']);

            Route::get('testimonials', [TestimonialHistoryController::class, 'index']);
            Route::post('testimonials', [TestimonialController::class, 'store']);
            Route::get('testimonials/{id}', [TestimonialHistoryController::class, 'show']);
        });

        // P2P Meeting Requests
        Route::post('/p2p-meeting-requests', [P2PMeetingRequestController::class, 'store']);
        Route::get('/p2p-meeting-requests/inbox', [P2PMeetingRequestController::class, 'inbox']);
        Route::get('/p2p-meeting-requests/sent', [P2PMeetingRequestController::class, 'sent']);
        Route::get('/p2p-meeting-requests/{id}', [P2PMeetingRequestController::class, 'show']);
        Route::post('/p2p-meeting-requests/{id}/accept', [P2PMeetingRequestController::class, 'accept']);
        Route::post('/p2p-meeting-requests/{id}/reject', [P2PMeetingRequestController::class, 'reject']);
        Route::post('/p2p-meeting-requests/{id}/cancel', [P2PMeetingRequestController::class, 'cancel']);

        // Admin Activities
        Route::get('/admin/activities', [AdminActivityController::class, 'index']);
        Route::get('/admin/activities/{activity}', [AdminActivityController::class, 'show']);
        Route::patch('/admin/activities/{id}', [AdminActivityController::class, 'updateStatus']);
        Route::patch('/admin/activities/{activity}/approve', [AdminActivityController::class, 'approve']);
        Route::patch('/admin/activities/{activity}/reject', [AdminActivityController::class, 'reject']);

        // Wallet
        Route::get('/wallet/transactions', [WalletController::class, 'myTransactions']);
        Route::post('/wallet/topup', [WalletController::class, 'topup']);

        // Requirements
        Route::get('/timeline/requirements', [TimelineRequirementController::class, 'index']);
        Route::post('/requirements', [V1RequirementController::class, 'store']);
        Route::get('/requirements/{id}', [V1RequirementController::class, 'show']);
        Route::patch('/requirements/{id}/close', [V1RequirementController::class, 'close']);
        Route::post('/requirements/{requirement}/interest', [RequirementInterestController::class, 'store']);
        Route::get('/my/requirements', [V1RequirementController::class, 'myIndex']);

        // Support
        Route::post('/support', [SupportController::class, 'store']);
        Route::get('/support/my', [SupportController::class, 'mySupportRequests']);
        Route::get('/support/admin', [SupportController::class, 'adminIndex']);
        Route::patch('/support/admin/{id}', [SupportController::class, 'adminUpdate']);

        // Chats & Messages
        Route::get('/chats', [ChatController::class, 'index']);
        Route::post('/chats', [ChatController::class, 'storeChat']);
        Route::get('/chats/{id}', [ChatController::class, 'showChat']);
        Route::get('/chats/{id}/messages', [ChatController::class, 'listMessages']);
        Route::post('/chats/{id}/messages', [ChatController::class, 'storeMessage']);
        Route::post('/messages/{message}/delete-for-me', [MessageDeletionController::class, 'deleteForMe']);
        Route::post('/messages/{message}/delete-for-everyone', [MessageDeletionController::class, 'deleteForEveryone']);
        Route::post('/chats/{chat}/typing/start', [ChatTypingController::class, 'start']);
        Route::post('/chats/{chat}/typing/stop', [ChatTypingController::class, 'stop']);
        Route::post('/chats/{id}/mark-read', [ChatController::class, 'markRead']);
        Route::post('/chats/{id}/typing', [ChatController::class, 'typing']);


        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Push tokens
        Route::post('/push-tokens', [PushTokenController::class, 'store']);
        Route::delete('/push-tokens', [PushTokenController::class, 'destroy']);

        if (app()->environment(['local', 'staging'])) {
            Route::post('/debug/push-test', function (\Illuminate\Http\Request $request) {
                $user = $request->user();

                \Illuminate\Support\Facades\Log::info('Dispatching test push job', [
                    'user_id' => $user->id,
                ]);

                \App\Jobs\SendPushNotificationJob::dispatch(
                    $user,
                    'Test Push',
                    'Hello from Laravel ✅',
                    [
                        'type' => 'test',
                        'time' => now()->toDateTimeString(),
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Push job dispatched',
                    'data' => [],
                ]);
            });
        }

        // Referrals & Visitors
        Route::get('/referrals/validate', [ReferralController::class, 'validateSelf']);
        Route::get('/referrals/me', [ReferralController::class, 'me']);
        Route::post('/referrals/generate', [ReferralController::class, 'generate']);
        Route::get('/referrals/members', [ReferralController::class, 'members']);
        Route::get('/referrals/stats', [ReferralController::class, 'stats']);
        Route::post('/referrals/links', [ReferralController::class, 'storeLink']);
        Route::get('/referrals/links', [ReferralController::class, 'listLinks']);
        Route::get('/referrals/visitors', [ReferralController::class, 'listVisitors']);
        Route::patch('/referrals/visitors/{id}', [ReferralController::class, 'updateVisitor']);

        // Files
        Route::post('/files/upload', [FileController::class, 'upload']);

        // Coin Claims
        Route::get('/coin-claims/activities', [CoinClaimController::class, 'activities']);
        Route::post('/coin-claims', [CoinClaimController::class, 'store']);
        Route::get('/coin-claims/my', [CoinClaimController::class, 'myRequests']);

        // Membership payments
        Route::post('/payments/create-order', [PaymentController::class, 'createOrder']);
        Route::post('/payments/verify', [PaymentController::class, 'verify']);

        // Forms
        Route::post('/forms/leader-interest', [LeaderInterestController::class, 'store']);
        Route::get('/forms/leader-interest/my', [LeaderInterestController::class, 'myIndex']);
        Route::post('/forms/recommend-peer', [PeerRecommendationController::class, 'store']);
        Route::get('/forms/recommend-peer/my', [PeerRecommendationController::class, 'myIndex']);
        Route::post('/forms/register-visitor', [VisitorRegistrationController::class, 'store']);
        Route::get('/forms/register-visitor/my', [VisitorRegistrationController::class, 'myIndex']);
        Route::get('/forms/visitor-registrations/my', [VisitorRegistrationController::class, 'myIndex']);

        // Website form submissions (read)
        Route::get('/become-a-mentor', [BecomeMentorController::class, 'index']);
        Route::get('/become-a-mentor/{id}', [BecomeMentorController::class, 'show'])->whereUuid('id');
        Route::get('/become-a-speaker', [WebsiteFormsController::class, 'indexBecomeSpeaker']);
        Route::get('/become-a-speaker/{id}', [WebsiteFormsController::class, 'showBecomeSpeaker'])->whereUuid('id');
        Route::get('/share-sme-business-story', [WebsiteFormsController::class, 'indexSmeBusinessStory']);
        Route::get('/share-sme-business-story/{id}', [WebsiteFormsController::class, 'showSmeBusinessStory'])->whereUuid('id');
        Route::get('/leadership-certification', [WebsiteFormsController::class, 'indexLeadershipCertification']);
        Route::get('/leadership-certification/{id}', [WebsiteFormsController::class, 'showLeadershipCertification'])->whereUuid('id');
        Route::get('/entrepreneur-certification', [WebsiteFormsController::class, 'indexEntrepreneurCertification']);
        Route::get('/entrepreneur-certification/{id}', [WebsiteFormsController::class, 'showEntrepreneurCertification'])->whereUuid('id');
        Route::get('/partner-with-us', [WebsiteFormsController::class, 'indexPartnerWithUs']);
        Route::get('/partner-with-us/{id}', [WebsiteFormsController::class, 'showPartnerWithUs'])->whereUuid('id');

        Route::get('/zoho/test-token', [ZohoDebugController::class, 'testToken']);
        Route::get('/zoho/org', [ZohoDebugController::class, 'org']);
        Route::post('/billing/checkout', [BillingCheckoutController::class, 'checkout']);
        Route::get('/billing/checkout/{hostedpage_id}', [BillingCheckoutController::class, 'status']);
        Route::get('/billing/hostedpages/{hostedpageId}/sync', [BillingCheckoutController::class, 'syncHostedPage']);
        Route::get('/circles/{circle}/package', [CircleSubscriptionController::class, 'package']);
        Route::post('/billing/circle-checkout/{circle}', [CircleSubscriptionController::class, 'checkout']);
    });

    Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
    Route::get('/zoho/plans', [ZohoPlansController::class, 'index']);
    Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);
    Route::post('/zoho/webhook', [ZohoWebhookController::class, 'handle']);
    Route::post('/billing/zoho/webhook', [ZohoBillingWebhookController::class, 'handle']);
    Route::post('/webhooks/zoho/circle-subscription', [ZohoBillingWebhookController::class, 'handleCircleSubscription']);
    Route::get('/billing/checkout/{hostedpage_id}/status', [BillingCheckoutController::class, 'status']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::get('/event-galleries', [EventGalleryApiController::class, 'index']);
    Route::get('/event-galleries/{id}', [EventGalleryApiController::class, 'show']);

    // Wallet payment webhook (called by payment gateway)
    Route::post('/wallet/webhook', [WalletController::class, 'paymentWebhook']);

    // Feedback (public, user optional)
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::post('/become-a-mentor', [BecomeMentorController::class, 'submit']);
    Route::post('/become-a-speaker', [WebsiteFormsController::class, 'submitBecomeSpeaker']);
    Route::post('/share-sme-business-story', [WebsiteFormsController::class, 'submitSmeBusinessStory']);
    Route::post('/leadership-certification', [WebsiteFormsController::class, 'submitLeadershipCertification']);
    Route::post('/entrepreneur-certification', [WebsiteFormsController::class, 'submitEntrepreneurCertification']);
    Route::post('/partner-with-us', [WebsiteFormsController::class, 'submitPartnerWithUs']);

    // Ads banners (public)
    Route::get('/ads/banners', [AdsController::class, 'index']);


    Route::get('/circulars', [CircularController::class, 'index']);
    Route::get('/circulars/{id}', [CircularController::class, 'show']);


    // Other module routes (members, circles, posts, etc.) will be added here later.
});
