defmodule Iso27001PhoenixWeb.Router do
  use Phoenix.Router
  import Plug.Conn
  import Phoenix.Controller

  alias Iso27001Phoenix.Core.Middleware.AuthPipeline

  pipeline :api do
    plug :accepts, ["json"]
  end

  pipeline :authenticated do
    plug AuthPipeline
  end

  scope "/api/v1", Iso27001PhoenixWeb.V1 do
    pipe_through :api

    # A.9: Auth endpoints (rate-limited to auth tier: 10/min)
    post "/auth/login",   AuthController, :login
    post "/auth/refresh", AuthController, :refresh
    post "/auth/logout",  AuthController, :logout

    # Public registration
    post "/users", UsersController, :create

    # Health — liveness and readiness are public
    get "/health/live",  HealthController, :live
    get "/health/ready", HealthController, :ready
  end

  scope "/api/v1", Iso27001PhoenixWeb.V1 do
    pipe_through [:api, :authenticated]

    get    "/users",     UsersController, :index
    get    "/users/me",  UsersController, :me
    get    "/users/:id", UsersController, :show
    patch  "/users/:id", UsersController, :update
    delete "/users/:id", UsersController, :delete

    # A.17: Detailed health — admin only (enforced inside controller)
    get "/health/detailed", HealthController, :detailed
  end

  # Prometheus metrics endpoint
  scope "/metrics" do
    pipe_through :api
    get "/", Iso27001PhoenixWeb.MetricsController, :index
  end
end
