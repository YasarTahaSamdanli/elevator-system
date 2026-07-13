import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_exception.dart';
import '../data/inspection_repository.dart';
import '../domain/inspection_summary.dart';
import '../domain/scanned_elevator.dart';

/// QR ile çözülen asansör için periyodik kontrol sonucu girişi.
/// Muayene raporundaki etiket, kusurlar ve tarihler backend'e gönderilir;
/// takip tarihi boşsa backend kırmızıda +30, sarıda +60 gün önerir.
class InspectionFormPage extends ConsumerStatefulWidget {
  const InspectionFormPage({super.key, required this.elevator});

  final ScannedElevator elevator;

  @override
  ConsumerState<InspectionFormPage> createState() =>
      _InspectionFormPageState();
}

class _InspectionFormPageState extends ConsumerState<InspectionFormPage> {
  static final _dateFormat = DateFormat('dd.MM.yyyy');

  String _type = 'periodic';
  String _label = 'green';
  DateTime _inspectedAt = DateTime.now();
  DateTime? _followUpDueDate;
  DateTime? _nextInspectionDate;
  final _bodyController = TextEditingController();
  final _reportController = TextEditingController();
  final _notesController = TextEditingController();
  final List<TextEditingController> _findingControllers = [];
  bool _submitting = false;

  @override
  void dispose() {
    _bodyController.dispose();
    _reportController.dispose();
    _notesController.dispose();
    for (final controller in _findingControllers) {
      controller.dispose();
    }
    super.dispose();
  }

  String _toApiDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  String? _trimmedOrNull(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  Future<void> _pickDate({
    required DateTime? initial,
    required ValueChanged<DateTime> onPicked,
  }) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: initial ?? DateTime.now(),
      firstDate: DateTime(2015),
      lastDate: DateTime(2035),
    );

    if (picked != null) {
      onPicked(picked);
    }
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);

    try {
      await ref.read(inspectionRepositoryProvider).create(
            elevatorUuid: widget.elevator.uuid,
            type: _type,
            inspectedAt: _toApiDate(_inspectedAt),
            label: _label,
            inspectionBody: _trimmedOrNull(_bodyController),
            reportNumber: _trimmedOrNull(_reportController),
            followUpDueDate:
                _followUpDueDate == null ? null : _toApiDate(_followUpDueDate!),
            nextInspectionDate: _nextInspectionDate == null
                ? null
                : _toApiDate(_nextInspectionDate!),
            notes: _trimmedOrNull(_notesController),
            findings: _findingControllers
                .map((controller) => controller.text.trim())
                .where((description) => description.isNotEmpty)
                .toList(),
          );

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(true);
    } on ApiException catch (exception) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(exception.message)),
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final elevator = widget.elevator;

    return Scaffold(
      appBar: AppBar(title: const Text('Periyodik Kontrol Girişi')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.elevator_outlined),
              title: Text(
                elevator.displayName,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              subtitle: Text(
                [
                  if (elevator.buildingName != null) elevator.buildingName!,
                  'Seri: ${elevator.serialNumber}',
                ].join(' — '),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Kontrol Tipi', style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 8),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'periodic', label: Text('Periyodik')),
              ButtonSegment(value: 'follow_up', label: Text('Takip Kontrolü')),
            ],
            selected: {_type},
            onSelectionChanged: (selection) =>
                setState(() => _type = selection.first),
          ),
          const SizedBox(height: 16),
          Text('Etiket', style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final label in inspectionLabels)
                ChoiceChip(
                  label: Text(label.name),
                  selected: _label == label.value,
                  selectedColor: label.color.withValues(alpha: 0.2),
                  avatar: CircleAvatar(
                    backgroundColor: label.color,
                    radius: 6,
                  ),
                  onSelected: (_) => setState(() => _label = label.value),
                ),
            ],
          ),
          const SizedBox(height: 16),
          _DateField(
            label: 'Kontrol Tarihi',
            value: _dateFormat.format(_inspectedAt),
            onTap: () => _pickDate(
              initial: _inspectedAt,
              onPicked: (date) => setState(() => _inspectedAt = date),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _bodyController,
            decoration: const InputDecoration(
              labelText: 'Muayene Kuruluşu',
              hintText: 'Örn: TSE',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reportController,
            decoration: const InputDecoration(
              labelText: 'Rapor No',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          _DateField(
            label: 'Takip Tarihi (boş = otomatik)',
            value: _followUpDueDate == null
                ? 'Kırmızı +30 gün / Sarı +60 gün'
                : _dateFormat.format(_followUpDueDate!),
            onTap: () => _pickDate(
              initial: _followUpDueDate,
              onPicked: (date) => setState(() => _followUpDueDate = date),
            ),
            onClear: _followUpDueDate == null
                ? null
                : () => setState(() => _followUpDueDate = null),
          ),
          const SizedBox(height: 12),
          _DateField(
            label: 'Sonraki Periyodik Kontrol',
            value: _nextInspectionDate == null
                ? 'Seçilmedi'
                : _dateFormat.format(_nextInspectionDate!),
            onTap: () => _pickDate(
              initial: _nextInspectionDate ??
                  DateTime.now().add(const Duration(days: 365)),
              onPicked: (date) => setState(() => _nextInspectionDate = date),
            ),
            onClear: _nextInspectionDate == null
                ? null
                : () => setState(() => _nextInspectionDate = null),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Kusurlar / Bulgular',
                style: Theme.of(context).textTheme.labelLarge,
              ),
              TextButton.icon(
                onPressed: () => setState(
                  () => _findingControllers.add(TextEditingController()),
                ),
                icon: const Icon(Icons.add),
                label: const Text('Bulgu Ekle'),
              ),
            ],
          ),
          if (_findingControllers.isEmpty)
            Text(
              'Kusur yoksa boş bırakın. Girilen kusurlar revizyon iş '
              'emrine kontrol listesi olarak kopyalanabilir.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          for (var i = 0; i < _findingControllers.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _findingControllers[i],
                      decoration: InputDecoration(
                        labelText: 'Kusur ${i + 1}',
                        border: const OutlineInputBorder(),
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Kaldır',
                    icon: const Icon(Icons.close),
                    onPressed: () => setState(
                      () => _findingControllers.removeAt(i).dispose(),
                    ),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 8),
          TextField(
            controller: _notesController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Notlar',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: _submitting ? null : _submit,
            icon: _submitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: const Text('Kontrolü Kaydet'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.onTap,
    this.onClear,
  });

  final String label;
  final String value;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          suffixIcon: onClear == null
              ? const Icon(Icons.calendar_today_outlined)
              : IconButton(
                  tooltip: 'Temizle',
                  icon: const Icon(Icons.close),
                  onPressed: onClear,
                ),
        ),
        child: Text(value),
      ),
    );
  }
}
