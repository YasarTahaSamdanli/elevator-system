import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../elevators/presentation/elevator_hub_page.dart';
import '../../inspections/data/inspection_repository.dart';
import '../domain/work_order.dart';
import '../domain/work_order_summary.dart';
import 'qr_scan_page.dart';
import 'work_orders_providers.dart';

class WorkOrdersPage extends ConsumerWidget {
  const WorkOrdersPage({super.key});

  Future<void> _startInspectionFlow(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);

    final qrIdentifier = await navigator.push<String>(
      MaterialPageRoute(builder: (_) => const QrScanPage()),
    );

    if (qrIdentifier == null || !context.mounted) {
      return;
    }

    try {
      final elevator = await ref
          .read(inspectionRepositoryProvider)
          .resolveByQr(qrIdentifier);

      if (!context.mounted) {
        return;
      }

      if (elevator == null) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text('QR koda kayitli bir asansor bulunamadi.'),
          ),
        );
        return;
      }

      // Asansor merkezi: kontroller + o asansorun is emirleri tek yerde.
      await navigator.push<void>(
        MaterialPageRoute(
          builder: (_) => ElevatorHubPage(elevator: elevator),
        ),
      );
    } on ApiException catch (exception) {
      messenger.showSnackBar(SnackBar(content: Text(exception.message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final workOrders = ref.watch(workOrdersProvider);
    final summary = ref.watch(workOrderSummaryProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Is Emirleri'),
        actions: [
          if (user != null)
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Center(
                child: Text(
                  user.name,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            ),
          IconButton(
            tooltip: 'Cikis Yap',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: workOrders.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ErrorState(
          message: error.toString(),
          onRetry: () => ref.invalidate(workOrdersProvider),
        ),
        data: (items) => _WorkOrdersDashboard(
          items: items,
          summary: summary,
          onRefresh: () {
            ref.invalidate(workOrderSummaryProvider);
            return ref.refresh(workOrdersProvider.future);
          },
          onScanInspection: () => _startInspectionFlow(context, ref),
        ),
      ),
    );
  }
}

class _WorkOrdersDashboard extends StatelessWidget {
  const _WorkOrdersDashboard({
    required this.items,
    required this.summary,
    required this.onRefresh,
    required this.onScanInspection,
  });

  final List<WorkOrder> items;
  final AsyncValue<WorkOrderSummary> summary;
  final Future<void> Function() onRefresh;
  final VoidCallback onScanInspection;

  bool _isToday(DateTime date) {
    final now = DateTime.now();
    return date.year == now.year &&
        date.month == now.month &&
        date.day == now.day;
  }

  @override
  Widget build(BuildContext context) {
    // "Bugünün Özeti" kartındaki "Bugün Planlı" ile aynı tanım: bugüne
    // planlanmış, iptal edilmemiş işler.
    final todayItems = items
        .where((item) =>
            item.scheduledAt != null &&
            _isToday(item.scheduledAt!) &&
            item.status != 'cancelled')
        .toList();

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          _ScanCard(onPressed: onScanInspection),
          const SizedBox(height: 12),
          _SummaryCard(summary: summary),
          const SizedBox(height: 12),
          _SectionTitle(title: 'Bugünkü İşler', count: todayItems.length),
          const SizedBox(height: 8),
          if (todayItems.isEmpty)
            const _EmptyInline(message: 'Bugüne planlı iş yok.')
          else
            ...todayItems.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _WorkOrderCard(workOrder: item),
              ),
            ),
          const SizedBox(height: 8),
          _SectionTitle(title: 'Tüm İşler', count: items.length),
          const SizedBox(height: 8),
          if (items.isEmpty)
            const _EmptyInline(message: 'Görüntülenecek iş emri yok.')
          else
            ...items.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _WorkOrderCard(workOrder: item),
              ),
            ),
        ],
      ),
    );
  }
}

class _ScanCard extends StatelessWidget {
  const _ScanCard({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor:
                      Theme.of(context).colorScheme.primaryContainer,
                  child: Icon(
                    Icons.qr_code_scanner,
                    color: Theme.of(context).colorScheme.onPrimaryContainer,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'QR ile Asansor Islemleri',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: onPressed,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('QR Oku'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Günün sayaçları. Sayılar backend'den (`/work-orders/summary`) gelir;
/// listedeki ilk sayfadan türetilmediği için kayıt sayısından bağımsız
/// olarak doğrudur.
class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  static final _dateFormat = DateFormat('dd.MM.yyyy');

  final AsyncValue<WorkOrderSummary> summary;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Bugünün Özeti',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                Text(
                  _dateFormat.format(DateTime.now()),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
            const SizedBox(height: 12),
            summary.when(
              loading: () => const _SummaryTiles(values: null),
              error: (_, __) => Text(
                'Özet yüklenemedi. Yenilemek için listeyi aşağı çekin.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              data: (data) => _SummaryTiles(values: data),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryTiles extends StatelessWidget {
  const _SummaryTiles({required this.values});

  /// null: yükleniyor — sayılar yerine "—" gösterilir.
  final WorkOrderSummary? values;

  @override
  Widget build(BuildContext context) {
    Widget tile({
      required int? value,
      required String label,
      required String caption,
      required IconData icon,
      required Color color,
    }) =>
        Expanded(
          child: _SummaryTile(
            value: value,
            label: label,
            caption: caption,
            icon: icon,
            color: color,
          ),
        );

    return Column(
      children: [
        Row(
          children: [
            tile(
              value: values?.scheduledToday,
              label: 'Bugün Planlı',
              caption: 'bugüne planlanan iş',
              icon: Icons.today_outlined,
              color: Colors.indigo,
            ),
            const SizedBox(width: 8),
            tile(
              value: values?.assigned,
              label: 'Atanmış',
              caption: 'başlanmayı bekliyor',
              icon: Icons.assignment_ind_outlined,
              color: Colors.orange,
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            tile(
              value: values?.inProgress,
              label: 'Devam Eden',
              caption: 'şu an çalışılıyor',
              icon: Icons.play_circle_outline,
              color: Colors.blue,
            ),
            const SizedBox(width: 8),
            tile(
              value: values?.completedToday,
              label: 'Bugün Biten',
              caption: 'bugün tamamlandı',
              icon: Icons.check_circle_outline,
              color: Colors.green,
            ),
          ],
        ),
      ],
    );
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.value,
    required this.label,
    required this.caption,
    required this.icon,
    required this.color,
  });

  final int? value;
  final String label;
  final String caption;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value?.toString() ?? '—',
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(color: color, fontWeight: FontWeight.w700),
                ),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(fontWeight: FontWeight.w600),
                ),
                Text(
                  caption,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.count});

  final String title;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(title, style: Theme.of(context).textTheme.titleMedium),
        ),
        Text(count.toString(), style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _EmptyInline extends StatelessWidget {
  const _EmptyInline({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Center(child: Text(message)),
      ),
    );
  }
}

class _WorkOrderCard extends StatelessWidget {
  const _WorkOrderCard({required this.workOrder});

  final WorkOrder workOrder;

  static final _dateFormat = DateFormat('dd.MM.yyyy HH:mm');

  @override
  Widget build(BuildContext context) {
    final subtitleParts = [
      if (workOrder.buildingName != null) workOrder.buildingName!,
      if (workOrder.elevatorName != null) workOrder.elevatorName!,
    ];

    return Card(
      child: ListTile(
        onTap: () => context.push('/work-orders/${workOrder.uuid}'),
        leading: CircleAvatar(
          backgroundColor: workOrder.statusColor.withValues(alpha: 0.15),
          child: Icon(Icons.build_outlined, color: workOrder.statusColor),
        ),
        title: Text(
          '${workOrder.workOrderNumber} - ${workOrder.typeLabel}',
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (subtitleParts.isNotEmpty) Text(subtitleParts.join(' - ')),
            const SizedBox(height: 4),
            Row(
              children: [
                _StatusChip(
                  label: workOrder.statusLabel,
                  color: workOrder.statusColor,
                ),
                const SizedBox(width: 8),
                if (workOrder.scheduledAt != null)
                  Text(
                    _dateFormat.format(workOrder.scheduledAt!),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.tonal(
              onPressed: onRetry,
              child: const Text('Tekrar Dene'),
            ),
          ],
        ),
      ),
    );
  }
}
