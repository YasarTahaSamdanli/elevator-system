import { Navigate, Route, Routes } from "react-router-dom";
import { ThemeProvider } from "@/providers/ThemeProvider";
import { AuthProvider, RequireAuth } from "@/providers/AuthProvider";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AppShell } from "@/layouts/AppShell";
import { DashboardPage } from "@/pages/DashboardPage";
import { BuildingsPage } from "@/pages/BuildingsPage";
import { ElevatorsPage } from "@/pages/ElevatorsPage";
import { WorkOrdersPage } from "@/pages/WorkOrdersPage";
import { ContractsPage } from "@/pages/ContractsPage";
import { UsersPage } from "@/pages/UsersPage";
import { LoginPage } from "@/pages/LoginPage";
import { NotFoundPage } from "@/pages/NotFoundPage";

export default function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <TooltipProvider delayDuration={200}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              element={
                <RequireAuth>
                  <AppShell />
                </RequireAuth>
              }
            >
              <Route index element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/buildings" element={<BuildingsPage />} />
              <Route path="/elevators" element={<ElevatorsPage />} />
              <Route path="/work-orders" element={<WorkOrdersPage />} />
              <Route path="/contracts" element={<ContractsPage />} />
              <Route path="/team" element={<UsersPage />} />
            </Route>
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </TooltipProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}
