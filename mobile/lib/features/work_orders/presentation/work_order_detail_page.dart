import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_exception.dart';
import '../data/work_order_repository.dart';
import '../domain/work_order.dart';
import 'qr_scan_page.dart';
import 'work_orders_providers.dart';

class WorkOrderDetailPage extends ConsumerStatefulWidget {
  const WorkOrderDetailPage({super.key, required this.uuid});

  final String uuid;

  @override
  ConsumerState<WorkOrderDetailPage> createState() =>
      _WorkOrderDetailPageState();
}

class _WorkOrderDetailPageState extends ConsumerState<WorkOrderDetailPage> {
  static final _dateFormat = DateFormat('dd.MM.yyyy HH:mm');

  bool _updating = false;
  bool _updatingMaterials = false;
  final Set<String> _updatingChecklistItems = {};
  final Set<String> _deletingMaterialItems = {};

  /// İşe başlama saha kanıtına bağlı: önce asansördeki QR etiketi
  /// okutulur, backend eşleşmeyi doğrulamadan iş `in_progress` olmaz.
  Future<void> _startWithQr() async {
    final qrIdentifier = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const QrScanPage()),
    );

    if (qrIdentifier == null || !mounted) {
      return;
    }

    await _updateStatus('in_progress', qrIdentifier: qrIdentifier);
  }

  Future<void> _updateStatus(String status, {String? qrIdentifier}) async {
    setState(() => _updating = true);

    try {
      await ref
          .read(workOrderRepositoryProvider)
          .updateStatus(widget.uuid, status, qrIdentifier: qrIdentifier);
      ref.invalidate(workOrderDetailProvider(widget.uuid));
      ref.invalidate(workOrdersProvider);
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_errorMessage(exception))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _updating = false);
      }
    }
  }

  Future<void> _toggleChecklistItem(WorkOrderChecklistItem item) async {
    setState(() => _updatingChecklistItems.add(item.uuid));

    try {
      await ref.read(workOrderRepositoryProvider).updateChecklistItem(
            widget.uuid,
            item.uuid,
            isDone: !item.isDone,
          );
      ref.invalidate(workOrderDetailProvider(widget.uuid));
      ref.invalidate(workOrdersProvider);
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_errorMessage(exception))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _updatingChecklistItems.remove(item.uuid));
      }
    }
  }

  Future<void> _editChecklistNote(WorkOrderChecklistItem item) async {
    final note = await showDialog<String?>(
      context: context,
      builder: (context) => _ChecklistNoteDialog(item: item),
    );

    if (!mounted || note == null) {
      return;
    }

    setState(() => _updatingChecklistItems.add(item.uuid));

    try {
      await ref.read(workOrderRepositoryProvider).updateChecklistItem(
            widget.uuid,
            item.uuid,
            note: note.trim().isEmpty ? null : note.trim(),
            includeNote: true,
          );
      ref.invalidate(workOrderDetailProvider(widget.uuid));
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_errorMessage(exception))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _updatingChecklistItems.remove(item.uuid));
      }
    }
  }

  Future<void> _addMaterialItem() async {
    final input = await showDialog<_MaterialItemInput>(
      context: context,
      builder: (context) => _MaterialItemDialog(
        materialsFuture: ref.read(workOrderRepositoryProvider).listMaterials(),
      ),
    );

    if (!mounted || input == null) {
      return;
    }

    setState(() => _updatingMaterials = true);

    try {
      await ref.read(workOrderRepositoryProvider).createItem(
            widget.uuid,
            materialUuid: input.material.uuid,
            quantity: input.quantity,
            note: input.note,
          );
      ref.invalidate(workOrderDetailProvider(widget.uuid));
      ref.invalidate(workOrdersProvider);
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_errorMessage(exception))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _updatingMaterials = false);
      }
    }
  }

  Future<void> _deleteMaterialItem(WorkOrderMaterialItem item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Malzemeyi Sil'),
        content: Text('${item.materialName} kaydÄ± silinsin mi?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('VazgeÃ§'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Sil'),
          ),
        ],
      ),
    );

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() => _deletingMaterialItems.add(item.uuid));

    try {
      await ref.read(workOrderRepositoryProvider).deleteItem(
            widget.uuid,
            item.uuid,
          );
      ref.invalidate(workOrderDetailProvider(widget.uuid));
      ref.invalidate(workOrdersProvider);
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_errorMessage(exception))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _deletingMaterialItems.remove(item.uuid));
      }
    }
  }

  String _errorMessage(ApiException exception) {
    if (exception.details.containsKey('qr_identifier')) {
      return 'Okutulan QR kod bu iş emrinin asansörüne ait değil. '
          'Doğru asansörde olduğunuzdan emin olun.';
    }

    return exception.message;
  }

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(workOrderDetailProvider(widget.uuid));
    final workOrder = detail.valueOrNull;
    final nextAction = workOrder?.nextAction;

    return Scaffold(
      appBar: AppBar(title: Text(workOrder?.workOrderNumber ?? 'İş Emri')),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(error.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton.tonal(
                  onPressed: () =>
                      ref.invalidate(workOrderDetailProvider(widget.uuid)),
                  child: const Text('Tekrar Dene'),
                ),
              ],
            ),
          ),
        ),
        data: (workOrder) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _section(context, 'Genel', [
              _row('Durum', workOrder.statusLabel),
              _row('Tip', workOrder.typeLabel),
              _row('Öncelik', workOrder.priorityLabel),
              if (workOrder.assignedUserName != null)
                _row('Atanan', workOrder.assignedUserName!),
            ]),
            _section(context, 'Konum', [
              if (workOrder.buildingName != null)
                _row('Bina', workOrder.buildingName!),
              if (workOrder.elevatorName != null)
                _row('Asansör', workOrder.elevatorName!),
              if (workOrder.elevatorSerialNumber != null)
                _row('Seri No', workOrder.elevatorSerialNumber!),
              if (workOrder.contractNumber != null)
                _row('Sözleşme', workOrder.contractNumber!),
            ]),
            _section(context, 'Zamanlama', [
              if (workOrder.scheduledAt != null)
                _row('Planlanan', _dateFormat.format(workOrder.scheduledAt!)),
              if (workOrder.startedAt != null)
                _row('Başlangıç', _dateFormat.format(workOrder.startedAt!)),
              if (workOrder.completedAt != null)
                _row('Bitiş', _dateFormat.format(workOrder.completedAt!)),
            ]),
            if (workOrder.checklist.isNotEmpty)
              _checklistSection(context, workOrder),
            if (workOrder.items.isNotEmpty || workOrder.canEditMaterials)
              _materialsSection(context, workOrder),
            if (workOrder.description != null || workOrder.notes != null)
              _section(context, 'Detay', [
                if (workOrder.description != null)
                  _row('Açıklama', workOrder.description!),
                if (workOrder.notes != null) _row('Notlar', workOrder.notes!),
              ]),
          ],
        ),
      ),
      bottomNavigationBar: nextAction == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton.icon(
                  onPressed: _updating
                      ? null
                      : () => nextAction.status == 'in_progress'
                          ? _startWithQr()
                          : _updateStatus(nextAction.status),
                  icon: _updating
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(
                          nextAction.status == 'in_progress'
                              ? Icons.qr_code_scanner
                              : Icons.check,
                        ),
                  label: Text(
                    nextAction.status == 'in_progress'
                        ? 'QR Okut ve İşe Başla'
                        : nextAction.label,
                  ),
                ),
              ),
            ),
    );
  }

  Widget _section(BuildContext context, String title, List<Widget> rows) {
    final visibleRows = rows.where((row) => row is! SizedBox).toList();
    if (visibleRows.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            ...visibleRows,
          ],
        ),
      ),
    );
  }

  Widget _checklistSection(BuildContext context, WorkOrder workOrder) {
    final completed = workOrder.completedChecklistCount;
    final total = workOrder.checklist.length;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Kontrol Listesi',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                Text(
                  '$completed/$total',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
            const SizedBox(height: 8),
            ..._groupedChecklist(context, workOrder.checklist),
          ],
        ),
      ),
    );
  }

  /// Maddeleri rapordaki bölüm sırasıyla (kırmızı → sarı → mavi → diğer)
  /// gruplayıp her grubun başına renkli başlık koyar. Hiçbir maddede renk
  /// yoksa (şablon checklist'i) başlıksız düz liste gösterilir.
  List<Widget> _groupedChecklist(
    BuildContext context,
    List<WorkOrderChecklistItem> checklist,
  ) {
    final hasSeverity = checklist.any((item) => item.severity != null);
    if (!hasSeverity) {
      return checklist.map(_checklistTile).toList();
    }

    final widgets = <Widget>[];

    for (final severity in [...ChecklistSeverityMeta.order, null]) {
      final items =
          checklist.where((item) => item.severity == severity).toList();
      if (items.isEmpty) {
        continue;
      }

      final meta =
          severity == null ? null : ChecklistSeverityMeta.bySeverity[severity];
      final done = items.where((item) => item.isDone).length;

      widgets.add(
        Padding(
          padding: const EdgeInsets.only(top: 10, bottom: 2),
          child: Row(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: meta?.color ?? Colors.grey,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  meta?.title ?? 'Diğer Maddeler',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              Text(
                '$done/${items.length}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      );
      widgets.addAll(items.map(_checklistTile));
    }

    return widgets;
  }

  Widget _checklistTile(WorkOrderChecklistItem item) {
    final updating = _updatingChecklistItems.contains(item.uuid);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: updating ? null : () => _toggleChecklistItem(item),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 48,
              height: 48,
              child: updating
                  ? const Padding(
                      padding: EdgeInsets.all(14),
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Checkbox(
                      value: item.isDone,
                      onChanged: (_) => _toggleChecklistItem(item),
                    ),
            ),
            const SizedBox(width: 4),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 13),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text.rich(
                      TextSpan(
                        children: [
                          if (item.itemCode != null)
                            TextSpan(
                              text: '${item.itemCode}  ',
                              style: const TextStyle(
                                color: Colors.black54,
                                fontFeatures: [FontFeature.tabularFigures()],
                              ),
                            ),
                          TextSpan(text: item.label),
                        ],
                      ),
                    ),
                    if (item.note != null && item.note!.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          item.note!,
                          style: const TextStyle(color: Colors.black54),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            IconButton(
              tooltip: 'Not',
              onPressed: updating ? null : () => _editChecklistNote(item),
              icon: Icon(
                item.note == null || item.note!.isEmpty
                    ? Icons.note_add_outlined
                    : Icons.sticky_note_2_outlined,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _materialsSection(BuildContext context, WorkOrder workOrder) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Kullanılan Malzemeler',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                if (workOrder.canEditMaterials)
                  IconButton(
                    tooltip: 'Malzeme ekle',
                    onPressed: _updatingMaterials ? null : _addMaterialItem,
                    icon: _updatingMaterials
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.add),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (workOrder.items.isEmpty)
              const Text('Henüz malzeme eklenmedi.')
            else
              ...workOrder.items.map(
                (item) => _materialTile(item, workOrder.canEditMaterials),
              ),
          ],
        ),
      ),
    );
  }

  Widget _materialTile(WorkOrderMaterialItem item, bool canDelete) {
    final code = item.materialCode;
    final unit = item.unit;
    final quantity = _formatNumber(item.quantity);
    final totalPrice = item.totalPrice;
    final deleting = _deletingMaterialItems.contains(item.uuid);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.inventory_2_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  code == null || code.isEmpty
                      ? item.materialName
                      : '$code - ${item.materialName}',
                ),
                Text(
                  unit == null || unit.isEmpty ? quantity : '$quantity $unit',
                  style: const TextStyle(color: Colors.black54),
                ),
                if (item.note != null && item.note!.isNotEmpty)
                  Text(
                    item.note!,
                    style: const TextStyle(color: Colors.black54),
                  ),
              ],
            ),
          ),
          if (totalPrice != null)
            Text(
              _formatMoney(totalPrice),
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          if (canDelete)
            IconButton(
              tooltip: 'Sil',
              onPressed: deleting ? null : () => _deleteMaterialItem(item),
              icon: deleting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.delete_outline),
            ),
        ],
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  String _formatNumber(num value) {
    if (value % 1 == 0) {
      return value.toInt().toString();
    }

    return value.toStringAsFixed(2);
  }

  String _formatMoney(num value) => '${_formatNumber(value)} TL';
}

class _ChecklistNoteDialog extends StatefulWidget {
  const _ChecklistNoteDialog({required this.item});

  final WorkOrderChecklistItem item;

  @override
  State<_ChecklistNoteDialog> createState() => _ChecklistNoteDialogState();
}

class _ChecklistNoteDialogState extends State<_ChecklistNoteDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.item.note ?? '');
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Kontrol Notu'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            widget.item.label,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _controller,
            autofocus: true,
            minLines: 3,
            maxLines: 5,
            decoration: const InputDecoration(
              labelText: 'Not',
              border: OutlineInputBorder(),
            ),
            textInputAction: TextInputAction.newline,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(''),
          child: const Text('Temizle'),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Vazgeç'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_controller.text),
          child: const Text('Kaydet'),
        ),
      ],
    );
  }
}

class _MaterialItemInput {
  const _MaterialItemInput({
    required this.material,
    required this.quantity,
    this.note,
  });

  final AvailableMaterial material;
  final num quantity;
  final String? note;
}

class _MaterialItemDialog extends StatefulWidget {
  const _MaterialItemDialog({required this.materialsFuture});

  final Future<List<AvailableMaterial>> materialsFuture;

  @override
  State<_MaterialItemDialog> createState() => _MaterialItemDialogState();
}

class _MaterialItemDialogState extends State<_MaterialItemDialog> {
  final _formKey = GlobalKey<FormState>();
  final _quantityController = TextEditingController(text: '1');
  final _noteController = TextEditingController();
  AvailableMaterial? _material;

  @override
  void dispose() {
    _quantityController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false) || _material == null) {
      return;
    }

    final quantity = num.parse(_quantityController.text.replaceAll(',', '.'));
    final note = _noteController.text.trim();

    Navigator.of(context).pop(
      _MaterialItemInput(
        material: _material!,
        quantity: quantity,
        note: note.isEmpty ? null : note,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Malzeme Ekle'),
      content: FutureBuilder<List<AvailableMaterial>>(
        future: widget.materialsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 120,
              child: Center(child: CircularProgressIndicator()),
            );
          }

          if (snapshot.hasError) {
            return const Text('Malzemeler yüklenemedi.');
          }

          final materials = snapshot.data ?? const [];
          if (materials.isEmpty) {
            return const Text('Aktif malzeme bulunamadı.');
          }

          return Form(
            key: _formKey,
            child: SizedBox(
              width: 420,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<AvailableMaterial>(
                    initialValue: _material,
                    decoration: const InputDecoration(
                      labelText: 'Malzeme',
                      border: OutlineInputBorder(),
                    ),
                    items: materials
                        .map(
                          (material) => DropdownMenuItem(
                            value: material,
                            child: Text(
                              material.label,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (material) => setState(() {
                      _material = material;
                    }),
                    validator: (value) =>
                        value == null ? 'Malzeme seçin.' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _quantityController,
                    decoration: InputDecoration(
                      labelText: _material?.unit.isNotEmpty == true
                          ? 'Miktar (${_material!.unit})'
                          : 'Miktar',
                      border: const OutlineInputBorder(),
                    ),
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    validator: (value) {
                      final quantity =
                          num.tryParse((value ?? '').replaceAll(',', '.'));
                      if (quantity == null || quantity <= 0) {
                        return 'Geçerli miktar girin.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _noteController,
                    minLines: 2,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Not',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Vazgeç'),
        ),
        FilledButton(
          onPressed: _submit,
          child: const Text('Ekle'),
        ),
      ],
    );
  }
}
