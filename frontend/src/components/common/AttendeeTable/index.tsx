import {Anchor, Avatar, Badge, Button, Table as MantineTable, Group} from '@mantine/core';
import {Attendee, MessageType} from "../../../types.ts";
import {IconMailForward, IconPlus, IconSend, IconTrash, IconUserCog, IconUpload} from "@tabler/icons-react";
import {getInitials, getProductFromEvent} from "../../../utilites/helpers.ts";
import {Table, TableHead} from "../Table";
import {useDisclosure} from "@mantine/hooks";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {NavLink, useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import Truncate from "../Truncate";
import {notifications} from "@mantine/notifications";
import {useModifyAttendee} from "../../../mutations/useModifyAttendee.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useResendAttendeeTicket} from "../../../mutations/useResendAttendeeTicket.ts";
import {ManageAttendeeModal} from "../../modals/ManageAttendeeModal";
import { ImportAttendeesModal } from "../../modals/ImportAttendeesModal";
import {ActionMenu} from '../ActionMenu';
import {AttendeeStatusBadge} from "../AttendeeStatusBadge";

interface AttendeeTableProps {
    attendees: Attendee[];
    openCreateModal: () => void;
}

export const AttendeeTable = ({attendees, openCreateModal}: AttendeeTableProps) => {
    const {eventId} = useParams();
    
    // Debug logs
    console.log('🔍 AttendeeTable rendered - eventId:', eventId);
    
    // Modal states with proper destructuring
    const [isMessageModalOpen, { open: openMessageModal, close: closeMessageModal }] = useDisclosure(false);
    const [isViewModalOpen, { open: openViewModal, close: closeViewModal }] = useDisclosure(false);
    const [isImportModalOpen, { open: openImportModal, close: closeImportModal }] = useDisclosure(false);
    
    console.log('🔍 Modal states:', {
        isImportModalOpen,
        isMessageModalOpen, 
        isViewModalOpen
    });
    
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee>();
    const {data: event} = useGetEvent(eventId);
    const modifyMutation = useModifyAttendee();
    const resendTicketMutation = useResendAttendeeTicket();

    const handleModalClick = (attendee: Attendee, modal: {
        open: () => void
    }) => {
        setSelectedAttendee(attendee);
        modal.open();
    }

    const handleResendTicket = (attendee: Attendee) => {
        resendTicketMutation.mutate({
            attendeeId: attendee.id,
            eventId: eventId,
        }, {
            onSuccess: () => showSuccess(t`Ticket email has been resent to attendee`),
            onError: (error: any) => showError(error.response.data.message || t`Failed to resend ticket email`)
        });
    }

    const handleCancel = (attendee: Attendee) => {
        const message = attendee.status === 'CANCELLED'
            ? t`Are you sure you want to activate this attendee?`
            : t`Are you sure you want to cancel this attendee? This will void their ticket`

        confirmationDialog(message, () => {
            modifyMutation.mutate({
                attendeeId: attendee.id,
                eventId: eventId,
                attendeeData: {
                    status: attendee.status === 'CANCELLED' ? 'ACTIVE' : 'CANCELLED'
                }
            }, {
                onSuccess: () => {
                    notifications.show({
                        message: (
                            <Trans>
                                Successfully {attendee.status === 'CANCELLED' ? 'activated' : 'cancelled'} attendee
                            </Trans>
                        ),
                        color: 'green',
                    });
                },
                onError: () => showError(t`Failed to cancel attendee`),
            });
        })
    };

    return (
        <>
            {/* Permanent header with buttons - always visible when there are attendees */}
            {attendees.length > 0 && (
                <Group mb="md">
                    <Button
                        leftSection={<IconPlus size={16} />}
                        color="green"
                        onClick={openCreateModal}
                    >
                        {t`Create Attendee`}
                    </Button>
                    <Button
                        variant="outline"
                        leftSection={<IconUpload size={16} />}
                        onClick={() => {
                            console.log('🚀 IMPORT BUTTON CLICKED');
                            console.log('eventId:', eventId);
                            openImportModal();
                        }}
                    >
                        {t`Import Attendees`}
                    </Button>
                </Group>
            )}

            {attendees.length === 0 ? (
                <NoResultsSplash
                    heading={t`No Attendees to show`}
                    imageHref={'/blank-slate/attendees.svg'}
                    subHeading={(
                        <>
                            <p>
                                {t`Your attendees will appear here once they have registered for your event. You can also manually add attendees.`}
                            </p>
                            <Group>
                                <Button
                                    size={'xs'}
                                    leftSection={<IconPlus/>}
                                    color={'green'}
                                    onClick={openCreateModal}
                                >
                                    {t`Manually add an Attendee`}
                                </Button>
                                <Button
                                    size={'xs'}
                                    variant="outline"
                                    leftSection={<IconUpload/>}
                                    onClick={() => {
                                        console.log('🚀 IMPORT BUTTON CLICKED (empty state)');
                                        openImportModal();
                                    }}
                                >
                                    {t`Import Attendees`}
                                </Button>
                            </Group>
                        </>
                    )}
                />
            ) : (
                <>
                    {/* Table section */}
                    <Table>
                        <TableHead>
                            <MantineTable.Tr>
                                <MantineTable.Th></MantineTable.Th>
                                <MantineTable.Th>{t`Name`}</MantineTable.Th>
                                <MantineTable.Th>{t`Email`}</MantineTable.Th>
                                <MantineTable.Th miw={140}>{t`Order`}</MantineTable.Th>
                                <MantineTable.Th>{t`Ticket`}</MantineTable.Th>
                                <MantineTable.Th miw={120}>{t`Status`}</MantineTable.Th>
                                <MantineTable.Th></MantineTable.Th>
                            </MantineTable.Tr>
                        </TableHead>
                        <MantineTable.Tbody>
                            {attendees.map((attendee) => {
                                return (
                                    <MantineTable.Tr key={attendee.id}>
                                        <MantineTable.Td>
                                            <Avatar
                                                size={40}>{getInitials(attendee.first_name + ' ' + attendee.last_name)}</Avatar>
                                        </MantineTable.Td>
                                        <MantineTable.Td>
                                            <b>
                                                <Truncate length={20}
                                                          text={attendee.first_name + ' ' + attendee.last_name}/>
                                            </b>
                                            <div>
                                                {attendee.public_id}
                                            </div>
                                        </MantineTable.Td>
                                        <MantineTable.Td>
                                            <Anchor target={'_blank'} href={`mailto:${attendee.email}`}>
                                                <Truncate length={25} text={attendee.email}/>
                                            </Anchor>
                                        </MantineTable.Td>
                                        <MantineTable.Td>
                                            <Anchor
                                                component={NavLink}
                                                to={`/manage/event/${eventId}/orders#order-${attendee.order?.id}`}>
                                                <Badge variant={'outline'} style={{cursor: 'pointer'}}>
                                                    {attendee.order?.public_id}
                                                </Badge>
                                            </Anchor>
                                        </MantineTable.Td>
                                        <MantineTable.Td>
                                            <Truncate
                                                text={getProductFromEvent(attendee.product_id, event)?.title}
                                                length={25}
                                            />
                                        </MantineTable.Td>
                                        <MantineTable.Td>
                                            <AttendeeStatusBadge attendee={attendee}/>
                                        </MantineTable.Td>
                                        <MantineTable.Td style={{paddingRight: 0}}>
                                            <ActionMenu itemsGroups={[
                                                {
                                                    label: t`Actions`,
                                                    items: [
                                                        {
                                                            label: t`Manage attendee`,
                                                            icon: <IconUserCog size={14}/>,
                                                            onClick: () => handleModalClick(attendee, { open: openViewModal }),
                                                        },
                                                        {
                                                            label: t`Message attendee`,
                                                            icon: <IconSend size={14}/>,
                                                            onClick: () => handleModalClick(attendee, { open: openMessageModal }),
                                                        },
                                                        {
                                                            label: t`Resend ticket email`,
                                                            icon: <IconMailForward size={14}/>,
                                                            onClick: () => handleResendTicket(attendee),
                                                            visible: attendee.status === 'ACTIVE',
                                                        },
                                                    ],
                                                },
                                                {
                                                    label: t`Danger Zone`,
                                                    items: [
                                                        {
                                                            label: attendee.status === 'CANCELLED' ? t`Activate` : t`Cancel` + ` ` + t`ticket`,
                                                            icon: <IconTrash size={14}/>,
                                                            onClick: () => handleCancel(attendee),
                                                            color: attendee.status === 'CANCELLED' ? 'green' : 'red',
                                                        },
                                                    ],
                                                },
                                            ]}/>
                                        </MantineTable.Td>
                                    </MantineTable.Tr>
                                );
                            })}
                        </MantineTable.Tbody>
                    </Table>
                </>
            )}

            {/* Modals */}
            {(selectedAttendee && isMessageModalOpen) && <SendMessageModal
                onClose={closeMessageModal}
                orderId={selectedAttendee.order_id}
                attendeeId={selectedAttendee.id}
                messageType={MessageType.IndividualAttendees}
            />}
            {(selectedAttendee?.id && isViewModalOpen) && <ManageAttendeeModal
                attendeeId={selectedAttendee.id}
                onClose={closeViewModal}
            />}
            <ImportAttendeesModal
                opened={isImportModalOpen}
                onClose={closeImportModal} 
                eventId={eventId}
                onImportComplete={() => {
                    closeImportModal();
                }}
            />
        </>
    );
};
