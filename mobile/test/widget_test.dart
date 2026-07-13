import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:asansor_mobile/app.dart';

void main() {
  testWidgets('app builds and shows the splash screen first',
      (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: AsansorApp()));

    expect(find.byType(SplashPage), findsOneWidget);
  });
}
