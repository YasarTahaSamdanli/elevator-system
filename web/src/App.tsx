import { Navigate, Route, Routes } from "react-router-dom";
import { ThemeProvider } from "@/providers/ThemeProvider";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AppShell } from "@/layouts/AppShell";
import { DashboardPage } from "@/pages/DashboardPage";
import { BuildingsPage } from "@/pages/BuildingsPage";
import { ElevatorsPage } from "@/pages/ElevatorsPage";
import { WorkOrdersPage } from "@/pages/WorkOrdersPage";
import { ContractsPage } from "@/pages/ContractsPage";
import { UsersPage } from "@/pages/UsersPage";
import { NotFoundPage } from "@/pages/NotFoundPage";

export default function App() {
  return (
    <ThemeProvider>
      <TooltipProvider delayDuration={200}>
        <Routes>
          <Route element={<AppShell />}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<DashboardPage />} />
            <Route path="/buildings" element={<BuildingsPage />} />
            <Route path="/elevators" element={<ElevatorsPage />} />
            <Route path="/work-orders" element={<WorkOrdersPage />} />
            <Route path="/contracts" element={<ContractsPage />} />
            <Route path="/team" element={<UsersPage />} />
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </TooltipProvider>
    </ThemeProvider>
  );
}
