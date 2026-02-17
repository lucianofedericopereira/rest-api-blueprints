defmodule Iso27001PhoenixWeb.MetricsController do
  @moduledoc "A.17: Prometheus /metrics endpoint."
  use Phoenix.Controller, formats: [:html, :json]

  def index(conn, _params) do
    conn
    |> put_resp_content_type("text/plain")
    |> send_resp(200, Prometheus.Format.Text.format())
  end
end
