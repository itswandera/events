import { useState } from 'react';
import { 
    Modal, 
    Button, 
    Group, 
    Text, 
    FileInput, 
    Stack, 
    Alert,
    List,
    Progress 
} from '@mantine/core';
import { IconUpload, IconDownload, IconAlertCircle } from '@tabler/icons-react';

interface ImportAttendeesModalProps {
    opened: boolean;
    onClose: () => void;
    eventId: string;
    onImportComplete: () => void;
}

interface ImportResult {
    imported: number;
    errors: string[];
}

export function ImportAttendeesModal({ 
    opened, 
    onClose, 
    eventId, 
    onImportComplete 
}: ImportAttendeesModalProps) {
    const [file, setFile] = useState<File | null>(null);
    const [loading, setLoading] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const [progress, setProgress] = useState(0);

    const handleDownloadSample = () => {
        const sampleData = [
            ['first_name', 'last_name', 'email', 'organization', 'ticket_type', 'amount_paid'],
            ['John', 'Doe', 'john@company.com', 'Acme Inc.', 'VIP Ticket', '5000'],
            ['Jane', 'Smith', 'jane@org.org', 'XYZ Corp', 'Standard Ticket', '3000']
        ];

        const csvContent = sampleData.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'attendees_sample.csv';
        link.click();
        window.URL.revokeObjectURL(url);
    };

    const handleImport = async () => {
        if (!file) return;
        
        setLoading(true);
        setImportResult(null);
        setProgress(0);

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('event_id', eventId);

        try {
            const response = await fetch('/api/attendees/import', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();
            
            if (response.ok) {
                setImportResult(result);
                if (result.imported > 0) {
                    onImportComplete();
                }
            } else {
                setImportResult({
                    imported: 0,
                    errors: [result.message || 'Import failed']
                });
            }
        } catch (error) {
            setImportResult({
                imported: 0,
                errors: ['Network error: Unable to import attendees']
            });
        } finally {
            setLoading(false);
            setProgress(100);
        }
    };

    const resetModal = () => {
        setFile(null);
        setImportResult(null);
        setProgress(0);
        onClose();
    };

    return (
        <Modal 
            opened={opened} 
            onClose={resetModal} 
            title="Import Attendees" 
            size="lg"
            closeOnClickOutside={!loading}
        >
            <Stack>
                <Text size="sm">
                    Import multiple attendees using a CSV file. Download the sample template to ensure correct formatting.
                </Text>
                
                <Group>
                    <Button 
                        onClick={handleDownloadSample} 
                        variant="outline" 
                        leftSection={<IconDownload size={16} />}
                    >
                        Download Sample CSV
                    </Button>
                </Group>

                <FileInput
                    label="CSV File"
                    placeholder="Select CSV file"
                    accept=".csv"
                    value={file}
                    onChange={setFile}
                    disabled={loading}
                    leftSection={<IconUpload size={16} />}
                />

                {loading && (
                    <Progress value={progress} size="lg" radius="xl" />
                )}

                {importResult && (
                    <div>
                        {importResult.imported > 0 && (
                            <Alert color="green" title="Success">
                                Successfully imported {importResult.imported} attendees.
                            </Alert>
                        )}
                        
                        {importResult.errors.length > 0 && (
                            <Alert 
                                color="red" 
                                title="Import Issues" 
                                icon={<IconAlertCircle size={16} />}
                                mt="md"
                            >
                                <Text size="sm" mb="xs">
                                    Some attendees could not be imported:
                                </Text>
                                <List size="sm">
                                    {importResult.errors.map((error, index) => (
                                        <List.Item key={index}>{error}</List.Item>
                                    ))}
                                </List>
                            </Alert>
                        )}
                    </div>
                )}

                <Group justify="flex-end" mt="md">
                    <Button 
                        variant="outline" 
                        onClick={resetModal}
                        disabled={loading}
                    >
                        Cancel
                    </Button>
                    <Button 
                        onClick={handleImport} 
                        loading={loading}
                        disabled={!file || loading}
                        color="primary"
                    >
                        Import Attendees
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
}
