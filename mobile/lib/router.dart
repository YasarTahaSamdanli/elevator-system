import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'app.dart';
import 'features/auth/presentation/auth_controller.dart';
import 'features/auth/presentation/login_page.dart';
import 'features/work_orders/presentation/work_order_detail_page.dart';
import 'features/work_orders/presentation/work_orders_page.dart';

final routerProvider = Provider<GoRouter>((ref) {
  // Auth durumu değiştiğinde redirect'in yeniden çalışması için sinyal.
  final refreshNotifier = ValueNotifier<int>(0);
  ref.onDispose(refreshNotifier.dispose);
  ref.listen(authControllerProvider, (_, __) => refreshNotifier.value++);

  return GoRouter(
    initialLocation: '/work-orders',
    refreshListenable: refreshNotifier,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);

      if (auth.isLoading) {
        return state.matchedLocation == '/splash' ? null : '/splash';
      }

      final loggedIn = auth.valueOrNull != null;
      final onLogin = state.matchedLocation == '/login';

      if (!loggedIn) {
        return onLogin ? null : '/login';
      }

      if (onLogin || state.matchedLocation == '/splash') {
        return '/work-orders';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashPage(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      GoRoute(
        path: '/work-orders',
        builder: (context, state) => const WorkOrdersPage(),
        routes: [
          GoRoute(
            path: ':uuid',
            builder: (context, state) =>
                WorkOrderDetailPage(uuid: state.pathParameters['uuid']!),
          ),
        ],
      ),
    ],
  );
});
